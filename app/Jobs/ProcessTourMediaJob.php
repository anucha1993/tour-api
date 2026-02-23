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

    public function __construct(
        int $tourId,
        ?string $pdfUrl,
        ?string $coverImageUrl,
        string $wholesalerCode,
        ?string $pdfHeaderImage = null,
        ?int $pdfHeaderHeight = null,
        ?string $pdfFooterImage = null,
        ?int $pdfFooterHeight = null,
    ) {
        $this->tourId = $tourId;
        $this->pdfUrl = $pdfUrl;
        $this->coverImageUrl = $coverImageUrl;
        $this->wholesalerCode = $wholesalerCode;
        $this->pdfHeaderImage = $pdfHeaderImage;
        $this->pdfHeaderHeight = $pdfHeaderHeight;
        $this->pdfFooterImage = $pdfFooterImage;
        $this->pdfFooterHeight = $pdfFooterHeight;
        $this->onQueue('media');
    }

    public function handle(): void
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

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTourMediaJob: Job failed', [
            'tour_id' => $this->tourId,
            'error' => $exception->getMessage(),
        ]);
    }
}
