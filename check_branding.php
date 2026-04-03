<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = \App\Models\WholesalerApiConfig::where('wholesaler_id', 1)->first();
echo "=== PDF Branding Config (Wholesaler 1) ===" . PHP_EOL;
echo "header_image: " . ($c->pdf_header_image ?? 'NULL') . PHP_EOL;
echo "footer_image: " . ($c->pdf_footer_image ?? 'NULL') . PHP_EOL;
echo "header_height: " . ($c->pdf_header_height ?? 'NULL') . PHP_EOL;
echo "footer_height: " . ($c->pdf_footer_height ?? 'NULL') . PHP_EOL;

// Check a sample tour to see current pdf_url
$tour = \App\Models\Tour::where('wholesaler_id', 1)->whereNotNull('pdf_url')->first();
if ($tour) {
    echo PHP_EOL . "=== Sample Tour ===" . PHP_EOL;
    echo "tour_id: " . $tour->id . PHP_EOL;
    echo "pdf_url: " . ($tour->pdf_url ?? 'NULL') . PHP_EOL;
    echo "pdf_branding_hash: " . ($tour->pdf_branding_hash ?? 'NULL') . PHP_EOL;
    echo "pdf_url is R2: " . (str_contains($tour->pdf_url ?? '', env('R2_URL', 'r2.dev')) ? 'YES' : 'NO') . PHP_EOL;
    echo "R2_URL env: " . env('R2_URL', 'NOT_SET') . PHP_EOL;
}
