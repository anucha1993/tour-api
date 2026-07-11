<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== integrations ที่ตั้งค่า PDF Branding (มี header/footer) ===\n";
$cfgs = DB::table('wholesaler_api_configs')
    ->where(function($q){ $q->whereNotNull('pdf_header_image')->orWhereNotNull('pdf_footer_image'); })
    ->get(['id','wholesaler_id','pdf_header_image','pdf_footer_image']);

foreach ($cfgs as $c) {
    $ws = DB::table('wholesalers')->where('id',$c->wholesaler_id)->first(['code','name']);
    $total  = DB::table('tours')->where('wholesaler_id',$c->wholesaler_id)->whereNotNull('pdf_url')->count();
    $branded= DB::table('tours')->where('wholesaler_id',$c->wholesaler_id)->whereNotNull('pdf_branding_hash')->count();
    echo sprintf("  cfg#%s [%s] pdf_tours=%d branded=%d\n", $c->id, $ws->code ?? '-', $total, $branded);

    // ดึง pdf 1 ตัวมาเช็คเวอร์ชัน PDF header
    $pdf = DB::table('tours')->where('wholesaler_id',$c->wholesaler_id)->whereNotNull('pdf_url')->value('pdf_url');
    if ($pdf) {
        $fh = @fopen($pdf,'rb');
        if ($fh) { $head = fread($fh,8); fclose($fh); echo "      sample PDF version: " . trim($head) . "\n"; }
        else { echo "      (เปิดไฟล์ตัวอย่างไม่ได้)\n"; }
    }
}
