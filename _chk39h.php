<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use setasign\Fpdi\Fpdi;

$tour = DB::table('tours')->where('wholesaler_id',9)->whereNotNull('pdf_url')->first(['pdf_url']);
$url = $tour->pdf_url;

// 1. download pdf
$resp = Http::timeout(60)->get($url);
echo "download: status={$resp->status()} bytes=".strlen($resp->body())."\n";
$tmp = sys_get_temp_dir().'/gs_test.pdf';
file_put_contents($tmp, $resp->body());
echo "pdf header bytes: ".substr($resp->body(),0,8)."\n";

// 2. try FPDI parse
try {
    $pdf = new Fpdi();
    $pages = $pdf->setSourceFile($tmp);
    echo "FPDI OK: pages=$pages\n";
} catch (\Throwable $e) {
    echo "FPDI FAILED: ".get_class($e)."\n  ".$e->getMessage()."\n";
}
@unlink($tmp);
