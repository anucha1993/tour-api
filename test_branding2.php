<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PdfBrandingService;

$c = \App\Models\WholesalerApiConfig::where('wholesaler_id', 1)->first();

// Use a sample wholesaler PDF URL (not R2)
$wholesalerPdfUrl = 'https://www.zegotravel.com/uploadfile/p_d_f/programtour/2575_20260401165752.pdf';

echo "Testing PDF branding with:" . PHP_EOL;
echo "PDF: " . $wholesalerPdfUrl . PHP_EOL;
echo "Header: " . $c->pdf_header_image . PHP_EOL;
echo "Footer: " . $c->pdf_footer_image . PHP_EOL;
echo PHP_EOL;

$service = new PdfBrandingService();
$service->setHeader($c->pdf_header_image, $c->pdf_header_height);
$service->setFooter($c->pdf_footer_image, $c->pdf_footer_height);

$result = $service->process($wholesalerPdfUrl);

if ($result) {
    echo "SUCCESS! Branded PDF saved to: " . $result . PHP_EOL;
    echo "File size: " . filesize($result) . " bytes" . PHP_EOL;
    
    // Copy to a known location for inspection
    $outputFile = __DIR__ . '/test_branded_output.pdf';
    copy($result, $outputFile);
    echo "Copied to: " . $outputFile . PHP_EOL;
    unlink($result);
} else {
    echo "FAILED! No output produced." . PHP_EOL;
    echo PHP_EOL . "Check storage/logs/laravel.log for details:" . PHP_EOL;
    $logs = file_get_contents(__DIR__ . '/storage/logs/laravel.log');
    $lines = explode("\n", $logs);
    $lastLines = array_slice($lines, -20);
    echo implode("\n", $lastLines);
}

$service->cleanup();
