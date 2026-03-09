<?php

namespace App\Jobs;

use App\Models\Tour;
use App\Services\CloudflareImagesService;
use App\Services\PdfBrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ProcessTourMediaJob - Async media processing (PDF + Cover Image)
 * 
 * แยก upload PDF/Image ออกจาก SyncToursJob เพื่อไม่ block sync loop
 * ทำงานใน queue 'media' แยกจาก sync queue
 */
class ProcessTourMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120; // 2 minutes per media item
    public array $backoff = [30, 60];

    protected int $tourId;
    protected ?string $pdfUrl;
    protected ?string $coverImageUrl;
    protected string $wholesalerCode;
    protected ?string $pdfHeaderImage;
    protected ?int $pdfHeaderHeight;
    protected ?string $pdfFooterImage;
    protected ?int $pdfFooterHeight;
    protected ?string $oldPdfUrl;
    protected ?string $oldCoverImageUrl;

    public function __construct(
        int $tourId,
        ?string $pdfUrl,
        ?string $coverImageUrl,
        string $wholesalerCode,
        ?string $pdfHeaderImage = null,
        ?int $pdfHeaderHeight = null,
        ?string $pdfFooterImage = null,
        ?int $pdfFooterHeight = null,
        ?string $oldPdfUrl = null,
        ?string $oldCoverImageUrl = null,
    ) {
        $this->tourId = $tourId;
        $this->pdfUrl = $pdfUrl;
        $this->coverImageUrl = $coverImageUrl;
        $this->wholesalerCode = $wholesalerCode;
        $this->pdfHeaderImage = $pdfHeaderImage;
        $this->pdfHeaderHeight = $pdfHeaderHeight;
        $this->pdfFooterImage = $pdfFooterImage;
        $this->pdfFooterHeight = $pdfFooterHeight;
        $this->oldPdfUrl = $oldPdfUrl;
        $this->oldCoverImageUrl = $oldCoverImageUrl;
        $this->onQueue('media');
    }

    public function handle(): void
    {
        try {
            $this->processMedia();
        } finally {
            // FIX: คืน connection กลับเมื่อ job เสร็จ ป้องกัน max_user_connections
            try {
                DB::disconnect();
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }

    protected function processMedia(): void
    {
        $tour = Tour::find($this->tourId);
        if (!$tour) {
            Log::warning('ProcessTourMediaJob: Tour not found', ['tour_id' => $this->tourId]);
            return;
        }

        $updates = [];

        // Process PDF
        if ($this->pdfUrl && str_starts_with($this->pdfUrl, 'http') && !str_contains($this->pdfUrl, env('R2_URL', ''))) {
            try {
                // Delete old PDF from R2 before uploading new one
                $this->deleteOldPdf($this->oldPdfUrl);

                $processedUrl = $this->uploadPdf();
                if ($processedUrl) {
                    $updates['pdf_url'] = $processedUrl;
                }
            } catch (\Exception $e) {
                Log::warning('ProcessTourMediaJob: Failed to upload PDF', [
                    'tour_id' => $this->tourId,
                    'url' => $this->pdfUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Process Cover Image
        if ($this->coverImageUrl && str_starts_with($this->coverImageUrl, 'http') && !str_contains($this->coverImageUrl, 'imagedelivery.net')) {
            try {
                // Delete old cover image from Cloudflare before uploading new one
                $this->deleteOldCoverImage($this->oldCoverImageUrl);

                $processedUrl = $this->uploadCoverImage();
                if ($processedUrl) {
                    $updates['cover_image_url'] = $processedUrl;
                }
            } catch (\Exception $e) {
                Log::warning('ProcessTourMediaJob: Failed to upload cover image', [
                    'tour_id' => $this->tourId,
                    'url' => $this->coverImageUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($updates)) {
            $tour->update($updates);
            Log::info('ProcessTourMediaJob: Updated tour media', [
                'tour_id' => $this->tourId,
                'fields' => array_keys($updates),
            ]);
        }
    }

    protected function uploadPdf(): ?string
    {
        $pdfBranding = null;
        if ($this->pdfHeaderImage || $this->pdfFooterImage) {
            $pdfBranding = new PdfBrandingService();
            $pdfBranding->setHeader($this->pdfHeaderImage, $this->pdfHeaderHeight);
            $pdfBranding->setFooter($this->pdfFooterImage, $this->pdfFooterHeight);
        }

        try {
            if ($pdfBranding) {
                $brandedPdfUrl = $pdfBranding->processAndUpload($this->pdfUrl, $this->wholesalerCode);
                if ($brandedPdfUrl) {
                    return $brandedPdfUrl;
                }
            }

            // Fallback: upload directly to R2
            $filename = pathinfo(parse_url($this->pdfUrl, PHP_URL_PATH), PATHINFO_FILENAME);
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename) . '_' . uniqid() . '.pdf';
            $r2Path = "pdfs/{$this->wholesalerCode}/" . date('Y/m') . "/{$filename}";

            $pdfContent = file_get_contents($this->pdfUrl);
            if ($pdfContent) {
                $disk = Storage::disk('r2');
                $disk->put($r2Path, $pdfContent, 'public');
                $r2Url = env('R2_URL');
                if ($r2Url) {
                    return rtrim($r2Url, '/') . '/' . $r2Path;
                }
                return $disk->url($r2Path);
            }
        } finally {
            if ($pdfBranding) {
                $pdfBranding->cleanup();
            }
        }

        return null;
    }

    protected function uploadCoverImage(): ?string
    {
        $cloudflare = app(CloudflareImagesService::class);
        if (!$cloudflare->isConfigured()) {
            return null;
        }

        $uploadResult = $cloudflare->uploadFromUrl($this->coverImageUrl, 'tour-cover-' . uniqid());
        if ($uploadResult && isset($uploadResult['id'])) {
            return $cloudflare->getDisplayUrl($uploadResult['id']);
        }

        return null;
    }

    /**
     * Delete old PDF from R2 storage if it exists
     */
    protected function deleteOldPdf(?string $oldPdfUrl): void
    {
        if (!$oldPdfUrl) return;

        $r2Url = env('R2_URL', '');
        if (!$r2Url || !str_contains($oldPdfUrl, $r2Url)) return;

        try {
            $r2Path = str_replace(rtrim($r2Url, '/') . '/', '', $oldPdfUrl);
            if ($r2Path && Storage::disk('r2')->exists($r2Path)) {
                Storage::disk('r2')->delete($r2Path);
                Log::info('ProcessTourMediaJob: Deleted old PDF from R2', [
                    'tour_id' => $this->tourId,
                    'path' => $r2Path,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('ProcessTourMediaJob: Failed to delete old PDF from R2', [
                'tour_id' => $this->tourId,
                'url' => $oldPdfUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete old cover image from Cloudflare Images if it exists
     */
    protected function deleteOldCoverImage(?string $oldCoverUrl): void
    {
        if (!$oldCoverUrl || !str_contains($oldCoverUrl, 'imagedelivery.net')) return;

        try {
            // Extract image ID from Cloudflare URL: https://imagedelivery.net/{account_hash}/{image_id}/{variant}
            $parts = explode('/', parse_url($oldCoverUrl, PHP_URL_PATH));
            // Path: /{account_hash}/{image_id}/{variant} → parts[0]='', parts[1]=hash, parts[2]=id, parts[3]=variant
            $imageId = $parts[2] ?? null;

            if ($imageId) {
                $cloudflare = app(CloudflareImagesService::class);
                if ($cloudflare->isConfigured()) {
                    $cloudflare->delete($imageId);
                    Log::info('ProcessTourMediaJob: Deleted old cover image from Cloudflare', [
                        'tour_id' => $this->tourId,
                        'image_id' => $imageId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('ProcessTourMediaJob: Failed to delete old cover image from Cloudflare', [
                'tour_id' => $this->tourId,
                'url' => $oldCoverUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTourMediaJob: Job failed', [
            'tour_id' => $this->tourId,
            'error' => $exception->getMessage(),
        ]);
    }
}
