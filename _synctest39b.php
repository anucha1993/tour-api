<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Services\PdfBrandingService;

$cfg = WholesalerApiConfig::find(39);
$wcode = $cfg->wholesaler?->code ?? 'GS';
$tour = Tour::where('wholesaler_id',9)->whereNotNull('pdf_url')->first(['id','wholesaler_tour_code','pdf_url']);

echo "tour #{$tour->id} {$tour->wholesaler_tour_code}\n";
echo "header={$cfg->pdf_header_image}\nfooter={$cfg->pdf_footer_image}\n\n";

$svc = new PdfBrandingService();
$svc->setHeader($cfg->pdf_header_image, $cfg->pdf_header_height);
$svc->setFooter($cfg->pdf_footer_image, $cfg->pdf_footer_height);

echo "Calling processAndUpload()...\n";
$url = $svc->processAndUpload($tour->pdf_url, $wcode);
$svc->cleanup();

echo "RESULT url = " . var_export($url, true) . "\n";
echo ($url ? "=> branded=TRUE (would set hash) ✅\n" : "=> returned NULL → fallback direct upload (hash NULL) ❌\n");
