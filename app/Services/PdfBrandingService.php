<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * PDF Branding Service
 * 
 * Overlays header and footer images onto PDF documents.
 * Used to brand wholesaler PDFs with company branding before storage.
 */
class PdfBrandingService
{
    protected ?string $headerImagePath = null;
    protected ?string $footerImagePath = null;
    protected ?int $headerHeight = null;
    protected ?int $footerHeight = null;

    /**
     * Set header image from URL
     */
    public function setHeader(?string $imageUrl, ?int $height = null): self
    {
        if ($imageUrl) {
            $this->headerImagePath = $this->downloadImage($imageUrl, 'header');
            $this->headerHeight = $height;
        }
        return $this;
    }

    /**
     * Set footer image from URL
     */
    public function setFooter(?string $imageUrl, ?int $height = null): self
    {
        if ($imageUrl) {
            $this->footerImagePath = $this->downloadImage($imageUrl, 'footer');
            $this->footerHeight = $height;
        }
        return $this;
    }

    /**
     * Process PDF - download, overlay header/footer, return processed path
     * 
     * @param string $pdfUrl URL of the original PDF
     * @return string|null Path to processed PDF (local temp file)
     */
    public function process(string $pdfUrl): ?string
    {
        try {
            // Download original PDF
            $originalPdfPath = $this->downloadPdf($pdfUrl);
            if (!$originalPdfPath) {
                Log::warning('PdfBrandingService: Failed to download PDF', ['url' => $pdfUrl]);
                return null;
            }

            // If no header and no footer, return original
            if (!$this->headerImagePath && !$this->footerImagePath) {
                return $originalPdfPath;
            }

            // Process PDF with FPDI
            $outputPath = $this->overlayImages($originalPdfPath);

            // Cleanup original if different from output
            if ($originalPdfPath !== $outputPath && file_exists($originalPdfPath)) {
                unlink($originalPdfPath);
            }

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('PdfBrandingService: Failed to process PDF', [
                'url' => $pdfUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Process PDF and save to public folder
     * 
     * @param string $pdfUrl Original PDF URL
     * @param string $wholesalerCode Wholesaler code for folder organization
     * @return string|null Public URL of branded PDF on R2
     */
    public function processAndUpload(string $pdfUrl, ?string $wholesalerCode = null): ?string
    {
        $processedPath = $this->process($pdfUrl);
        
        if (!$processedPath) {
            return null;
        }

        try {
            // Build organized path: pdfs/{wholesaler_code}/{year}/{month}/filename.pdf
            $wholesalerFolder = $wholesalerCode ? strtolower($wholesalerCode) : 'default';
            $yearMonth = date('Y/m');
            $filename = pathinfo(parse_url($pdfUrl, PHP_URL_PATH), PATHINFO_FILENAME);
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename) . '_' . uniqid() . '.pdf';
            
            $r2Path = "pdfs/{$wholesalerFolder}/{$yearMonth}/{$filename}";
            
            // Upload to Cloudflare R2
            $disk = Storage::disk('r2');
            $disk->put($r2Path, file_get_contents($processedPath), 'public');
            
            // Cleanup temp file
            if (file_exists($processedPath)) {
                unlink($processedPath);
            }

            // Return R2 public URL
            // Format: https://{bucket}.{account_id}.r2.cloudflarestorage.com/{path}
            // Or use custom domain if configured in R2_URL
            $r2Url = env('R2_URL');
            if ($r2Url) {
                return rtrim($r2Url, '/') . '/' . $r2Path;
            }
            
            // Fallback: return the path (will need R2_URL configured)
            return $disk->url($r2Path);

        } catch (\Exception $e) {
            Log::error('PdfBrandingService: Failed to upload PDF to R2', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Overlay header and footer images on all pages
     */
    protected function overlayImages(string $pdfPath): string
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($pdfPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // Import page
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            
            // Add page with same size
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            
            // Use imported page as template
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

            // Overlay header (top of page)
            if ($this->headerImagePath && file_exists($this->headerImagePath)) {
                $this->addHeaderOverlay($pdf, $size);
            }

            // Overlay footer (bottom of page)
            if ($this->footerImagePath && file_exists($this->footerImagePath)) {
                $this->addFooterOverlay($pdf, $size);
            }
        }

        // Save to temp file
        $outputPath = sys_get_temp_dir() . '/branded_' . uniqid() . '.pdf';
        $pdf->Output($outputPath, 'F');

        return $outputPath;
    }

    /**
     * Add header image overlay
     * ขนาด: 210mm กว้าง (เต็มหน้า A4), ความสูงตาม aspect ratio
     * ตำแหน่ง: x=0, y=0 (มุมบนซ้าย)
     */
    protected function addHeaderOverlay(Fpdi $pdf, array $pageSize): void
    {
        if (!file_exists($this->headerImagePath)) return;

        // ใช้ความกว้างเต็มหน้า A4 = 210mm
        // height = 0 ให้ FPDI คำนวณอัตโนมัติรักษา aspect ratio
        $pdf->Image($this->headerImagePath, 0, 0, 210, 0);
    }

    /**
     * Add footer image overlay
     * ขนาด: 210mm กว้าง (เต็มหน้า A4), ความสูงตาม aspect ratio
     * ตำแหน่ง: x=0, y=คำนวณจากความสูงภาพ
     */
    protected function addFooterOverlay(Fpdi $pdf, array $pageSize): void
    {
        if (!file_exists($this->footerImagePath)) return;

        $imageInfo = getimagesize($this->footerImagePath);
        if (!$imageInfo) return;

        $imgWidthPx = $imageInfo[0];
        $imgHeightPx = $imageInfo[1];
        
        // คำนวณความสูงตาม aspect ratio สำหรับหาตำแหน่ง y
        // width 210mm, height = 210 * (imgHeight / imgWidth)
        $displayHeight = 210 * $imgHeightPx / $imgWidthPx;
        
        // วางที่ด้านล่าง
        $pageHeight = $pageSize['height']; // ปกติ 297mm สำหรับ A4
        $y = $pageHeight - $displayHeight;
        
        // height = 0 ให้ FPDI คำนวณอัตโนมัติรักษา aspect ratio
        $pdf->Image($this->footerImagePath, 0, $y, 210, 0);
    }

    /**
     * Download PDF to temp file
     */
    protected function downloadPdf(string $url): ?string
    {
        try {
            $response = Http::timeout(60)->get($url);
            
            if (!$response->successful()) {
                return null;
            }

            $tempPath = sys_get_temp_dir() . '/pdf_' . uniqid() . '.pdf';
            file_put_contents($tempPath, $response->body());

            return $tempPath;

        } catch (\Exception $e) {
            Log::error('PdfBrandingService: Failed to download PDF', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Download image to temp file
     */
    protected function downloadImage(string $url, string $prefix = 'img'): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);
            
            if (!$response->successful()) {
                return null;
            }

            // Determine extension from content type
            $contentType = $response->header('Content-Type');
            $ext = match ($contentType) {
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/gif' => 'gif',
                default => 'png',
            };

            $tempPath = sys_get_temp_dir() . "/{$prefix}_" . uniqid() . ".{$ext}";
            file_put_contents($tempPath, $response->body());

            return $tempPath;

        } catch (\Exception $e) {
            Log::error('PdfBrandingService: Failed to download image', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Cleanup temp files
     */
    public function cleanup(): void
    {
        if ($this->headerImagePath && file_exists($this->headerImagePath)) {
            unlink($this->headerImagePath);
        }
        if ($this->footerImagePath && file_exists($this->footerImagePath)) {
            unlink($this->footerImagePath);
        }
    }

    /**
     * Destructor - cleanup temp files
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
