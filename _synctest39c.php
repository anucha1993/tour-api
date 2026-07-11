<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Services\PdfBrandingService;
use Illuminate\Support\Facades\Http;

$cfg = WholesalerApiConfig::find(39);
$wcode = $cfg->wholesaler?->code ?? 'GS';

// หา tour ของ GS ที่ไฟล์ PDF ยังเข้าถึงได้จริง (ไม่ 404)
$candidates = Tour::where('wholesaler_id',9)->whereNotNull('pdf_url')
    ->whereNull('pdf_branding_hash')->limit(20)->get(['id','wholesaler_tour_code','pdf_url']);

$tour = null;
foreach ($candidates as $c) {
    $head = @get_headers($c->pdf_url);
    if ($head && strpos($head[0],'200') !== false) { $tour = $c; break; }
}
if (!$tour) { echo "ไม่พบ tour ที่ไฟล์ PDF ยังเข้าถึงได้\n"; exit; }

echo "ทดสอบ tour #{$tour->id} {$tour->wholesaler_tour_code} (ไฟล์ยังอยู่บน R2)\n\n";

$svc = new PdfBrandingService();
$svc->setHeader($cfg->pdf_header_image, $cfg->pdf_header_height);
$svc->setFooter($cfg->pdf_footer_image, $cfg->pdf_footer_height);

echo "processAndUpload() → brand + upload ขึ้น R2 ...\n";
$url = $svc->processAndUpload($tour->pdf_url, $wcode);
$svc->cleanup();

echo "\nRESULT url = " . var_export($url, true) . "\n";
if ($url) {
    $hash = md5(($cfg->pdf_header_image ?? '').'|'.($cfg->pdf_footer_image ?? ''));
    echo "=> BRANDING สำเร็จ ✅  (branded=true, hash=$hash จะถูก insert)\n";
    // verify uploaded file reachable
    $h = @get_headers($url);
    echo "   ไฟล์ branded บน R2: " . ($h[0] ?? 'n/a') . "\n";
} else {
    echo "=> ยัง fail ❌\n";
}
