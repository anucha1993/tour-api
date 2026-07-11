<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$cfg = DB::table('wholesaler_api_configs')->where('id',39)->first();
echo "integration_type=".($cfg->integration_type??'-')."\n";
echo "sync_method=".($cfg->sync_method??'-')." sync_mode=".($cfg->sync_mode??'-')."\n";
echo "api_base_url=".($cfg->api_base_url??'-')."\n";
echo "headcode_file=".($cfg->headcode_file??'-')."\n\n";

// what pdf_url do existing tours store - are they files.nexttrip.world or pub-...r2.dev?
echo "=== pdf_url domains distribution (wholesaler 9) ===\n";
$rows = DB::table('tours')->where('wholesaler_id',9)->whereNotNull('pdf_url')->pluck('pdf_url');
$dom = [];
foreach ($rows as $u) {
    $h = parse_url($u, PHP_URL_HOST) ?: 'other';
    $dom[$h] = ($dom[$h]??0)+1;
}
foreach ($dom as $h=>$c) echo "  $h : $c\n";

echo "\n=== check tours with branding_hash NULL but pdf on r2 (should rebrand) ===\n";
$n = DB::table('tours')->where('wholesaler_id',9)->whereNotNull('pdf_url')->whereNull('pdf_branding_hash')->count();
echo "  tours needing branding: $n\n";
