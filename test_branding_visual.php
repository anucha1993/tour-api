<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PdfBrandingService;

$c = \App\Models\WholesalerApiConfig::where('wholesaler_id', 1)->first();

$wholesalerPdfUrl = 'https://www.zegotravel.com/uploadfile/p_d_f/programtour/2575_20260401165752.pdf';

echo "=== Testing PDF Branding ===" . PHP_EOL;
echo "Header: " . $c->pdf_header_image . PHP_EOL;
echo "Footer: " . $c->pdf_footer_image . PHP_EOL;

// Download header image to check
$headerResp = \Illuminate\Support\Facades\Http::timeout(30)->get($c->pdf_header_image);
$headerTmp = sys_get_temp_dir() . '/test_header.jpg';
file_put_contents($headerTmp, $headerResp->body());
$headerInfo = getimagesize($headerTmp);
echo "Header image: {$headerInfo[0]}x{$headerInfo[1]} px, type=" . image_type_to_mime_type($headerInfo[2]) . PHP_EOL;

// Download footer image to check
$footerResp = \Illuminate\Support\Facades\Http::timeout(30)->get($c->pdf_footer_image);
$footerTmp = sys_get_temp_dir() . '/test_footer.jpg';
file_put_contents($footerTmp, $footerResp->body());
$footerInfo = getimagesize($footerTmp);
echo "Footer image: {$footerInfo[0]}x{$footerInfo[1]} px, type=" . image_type_to_mime_type($footerInfo[2]) . PHP_EOL;

// Calculate display sizes for A4 (210mm wide)
$headerDisplayH = 210 * $headerInfo[1] / $headerInfo[0];
$footerDisplayH = 210 * $footerInfo[1] / $footerInfo[0];
echo "Header display: 210mm x {$headerDisplayH}mm" . PHP_EOL;
echo "Footer display: 210mm x {$footerDisplayH}mm" . PHP_EOL;

// Download PDF to check page size
$pdfResp = \Illuminate\Support\Facades\Http::timeout(60)->get($wholesalerPdfUrl);
$pdfTmp = sys_get_temp_dir() . '/test_source.pdf';
file_put_contents($pdfTmp, $pdfResp->body());

// Check PDF page size with FPDI
$fpdi = new \setasign\Fpdi\Fpdi();
$pageCount = $fpdi->setSourceFile($pdfTmp);
$tplId = $fpdi->importPage(1);
$size = $fpdi->getTemplateSize($tplId);
echo PHP_EOL . "PDF page 1 size: {$size['width']}mm x {$size['height']}mm (orientation: {$size['orientation']})" . PHP_EOL;
echo "Page count: {$pageCount}" . PHP_EOL;

// Now brand it
echo PHP_EOL . "=== Branding PDF ===" . PHP_EOL;
$service = new PdfBrandingService();
$service->setHeader($c->pdf_header_image, $c->pdf_header_height);
$service->setFooter($c->pdf_footer_image, $c->pdf_footer_height);
$result = $service->process($wholesalerPdfUrl);

if ($result) {
    $output = __DIR__ . '/test_branded_check.pdf';
    copy($result, $output);
    echo "Branded PDF: {$output}" . PHP_EOL;
    echo "File size: " . filesize($output) . " bytes" . PHP_EOL;
    unlink($result);
} else {
    echo "BRANDING FAILED!" . PHP_EOL;
}

// Cleanup
unlink($headerTmp);
unlink($footerTmp);
unlink($pdfTmp);
$service->cleanup();
