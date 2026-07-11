<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\PdfBrandingService;

$tour = DB::table('tours')->where('wholesaler_id',9)->whereNotNull('pdf_url')->first(['id','wholesaler_tour_code','pdf_url']);
echo "Testing tour #{$tour->id} {$tour->wholesaler_tour_code}\n{$tour->pdf_url}\n\n";

$header = "https://imagedelivery.net/OGiukopN6pbQwdTofcZnpg/integration-header-39-1783684650/public";
$footer = "https://imagedelivery.net/OGiukopN6pbQwdTofcZnpg/integration-footer-39-1783684657/public";

$svc = new PdfBrandingService();
$svc->setHeader($header, 45);
$svc->setFooter($footer, 45);

// call process() (not upload) to isolate branding
try {
    $out = $svc->process($tour->pdf_url);
    if ($out) {
        echo "process() OK -> $out (" . filesize($out) . " bytes)\n";
        copy($out, __DIR__.'/_test_branded_39.pdf');
        echo "saved copy to _test_branded_39.pdf\n";
    } else {
        echo "process() returned NULL (branding FAILED)\n";
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: ".get_class($e).": ".$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
$svc->cleanup();
