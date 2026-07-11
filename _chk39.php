<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\Tour;

$c = WholesalerApiConfig::find(39);
if (!$c) { echo "config id 39 not found\n"; }
else {
    echo json_encode([
        'id' => $c->id,
        'wholesaler_id' => $c->wholesaler_id,
        'code' => $c->wholesaler?->code,
        'pdf_header_image' => $c->pdf_header_image,
        'pdf_footer_image' => $c->pdf_footer_image,
        'pdf_header_height' => $c->pdf_header_height,
        'pdf_footer_height' => $c->pdf_footer_height,
        'sync_enabled' => $c->sync_enabled,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";

    // Sample tours for this wholesaler
    $wid = $c->wholesaler_id;
    $total = Tour::where('wholesaler_id', $wid)->count();
    $withPdf = Tour::where('wholesaler_id', $wid)->whereNotNull('pdf_url')->count();
    $branded = Tour::where('wholesaler_id', $wid)->whereNotNull('pdf_branding_hash')->count();
    echo "wholesaler_id=$wid total_tours=$total with_pdf=$withPdf branded_hash=$branded\n";

    $samples = Tour::where('wholesaler_id', $wid)->whereNotNull('pdf_url')->limit(5)
        ->get(['id','wholesaler_tour_code','pdf_url','pdf_branding_hash']);
    foreach ($samples as $t) {
        echo "  #{$t->id} {$t->wholesaler_tour_code} hash=" . ($t->pdf_branding_hash ?? 'NULL') . " pdf=" . substr($t->pdf_url,0,80) . "\n";
    }
}
