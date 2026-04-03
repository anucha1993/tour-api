<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Reset pdf_branding_hash for all tours that have it set
// This forces re-branding on next sync
$count = \App\Models\Tour::whereNotNull('pdf_branding_hash')
    ->update(['pdf_branding_hash' => null]);

echo "Reset pdf_branding_hash for {$count} tours" . PHP_EOL;

// Also check which wholesalers have branding configured
$configs = \App\Models\WholesalerApiConfig::whereNotNull('pdf_header_image')
    ->orWhereNotNull('pdf_footer_image')
    ->get(['id', 'wholesaler_id', 'pdf_header_image', 'pdf_footer_image']);

echo PHP_EOL . "Wholesalers with PDF branding:" . PHP_EOL;
foreach ($configs as $c) {
    echo "  Config #{$c->id} (wholesaler #{$c->wholesaler_id}): header=" . ($c->pdf_header_image ? 'YES' : 'no') . " footer=" . ($c->pdf_footer_image ? 'YES' : 'no') . PHP_EOL;
}
