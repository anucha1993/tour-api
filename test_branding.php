<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PdfBrandingService;

$c = \App\Models\WholesalerApiConfig::where('wholesaler_id', 1)->first();

echo "Header image: " . $c->pdf_header_image . PHP_EOL;
echo "Footer image: " . $c->pdf_footer_image . PHP_EOL;

// Test download header image
echo PHP_EOL . "=== Testing header image download ===" . PHP_EOL;
try {
    $response = \Illuminate\Support\Facades\Http::timeout(30)->get($c->pdf_header_image);
    echo "Status: " . $response->status() . PHP_EOL;
    echo "Content-Type: " . $response->header('Content-Type') . PHP_EOL;
    echo "Body size: " . strlen($response->body()) . " bytes" . PHP_EOL;
    $ok = $response->successful();
    echo "Successful: " . ($ok ? 'YES' : 'NO') . PHP_EOL;
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}

// Test a sample tour PDF
$tour = \App\Models\Tour::where('wholesaler_id', 1)
    ->whereNotNull('pdf_url')
    ->where('pdf_url', 'LIKE', '%r2.dev%')
    ->first();

if ($tour) {
    echo PHP_EOL . "=== Testing Tour PDF ===" . PHP_EOL;
    echo "Tour: " . $tour->id . " - " . $tour->title . PHP_EOL;
    echo "PDF URL: " . $tour->pdf_url . PHP_EOL;

    // Find the original wholesaler URL from external_id
    // Just test with the wholesaler API URL to see if branding service works
    
    // Test PdfBrandingService e2e
    echo PHP_EOL . "=== Testing PdfBrandingService ===" . PHP_EOL;
    $service = new PdfBrandingService();
    $service->setHeader($c->pdf_header_image, $c->pdf_header_height);
    echo "Header path set: " . (new ReflectionProperty($service, 'headerImagePath'))->getValue($service) . PHP_EOL;
    $service->setFooter($c->pdf_footer_image, $c->pdf_footer_height);
    echo "Footer path set: " . (new ReflectionProperty($service, 'footerImagePath'))->getValue($service) . PHP_EOL;

    // Check if files exist
    $headerPath = (new ReflectionProperty($service, 'headerImagePath'))->getValue($service);
    $footerPath = (new ReflectionProperty($service, 'footerImagePath'))->getValue($service);
    echo "Header file exists: " . (file_exists($headerPath) ? 'YES (' . filesize($headerPath) . ' bytes)' : 'NO') . PHP_EOL;
    echo "Footer file exists: " . (file_exists($footerPath) ? 'YES (' . filesize($footerPath) . ' bytes)' : 'NO') . PHP_EOL;

    $service->cleanup();
}
