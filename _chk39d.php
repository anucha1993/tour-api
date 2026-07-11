<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== recent sync logs wholesaler 9 (GS) ===\n";
$logs = DB::table('sync_logs')->where('wholesaler_id',9)->orderByDesc('id')->limit(8)
    ->get(['id','status','sync_type','tours_created','tours_updated','started_at','completed_at']);
foreach ($logs as $l) {
    echo "  #{$l->id} {$l->status} {$l->sync_type} created={$l->tours_created} updated={$l->tours_updated} start={$l->started_at} end={$l->completed_at}\n";
}

echo "\n=== 18 pending media jobs: inspect payload for tour/branding ===\n";
$jobs = DB::table('jobs')->where('queue','media')->orderBy('id')->limit(3)->get();
foreach ($jobs as $j) {
    $body = json_decode($j->payload, true);
    $cmd = $body['data']['command'] ?? '';
    // Extract header/footer presence roughly
    $hasHeader = strpos($cmd,'imagedelivery.net') !== false;
    echo "  job#{$j->id} attempts={$j->attempts} available_at=".date('Y-m-d H:i:s',$j->available_at)." reserved_at=".($j->reserved_at?date('Y-m-d H:i:s',$j->reserved_at):'null')." hasBrandingImg=".($hasHeader?'YES':'no')."\n";
}

echo "\n=== config updated_at vs last completed sync ===\n";
$cfg = DB::table('wholesaler_api_configs')->where('id',39)->first(['updated_at','pdf_header_image']);
echo "  config.updated_at=".$cfg->updated_at."\n";
$lastDone = DB::table('sync_logs')->where('wholesaler_id',9)->where('status','completed')->orderByDesc('id')->first(['id','finished_at']);
echo "  last completed sync: #".($lastDone->id??'-')." at ".($lastDone->finished_at??'-')."\n";
