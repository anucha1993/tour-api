<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$branded = DB::table('tours')->where('wholesaler_id',9)->whereNotNull('pdf_branding_hash')->count();
$total   = DB::table('tours')->where('wholesaler_id',9)->whereNotNull('pdf_url')->count();
echo "GS tours: branded=$branded / pdf_total=$total\n\n";

echo "=== media queue pending/failed ===\n";
echo "pending media jobs: " . DB::table('jobs')->where('queue','media')->count() . "\n";
$ff = DB::table('failed_jobs')->where('payload','like','%ProcessTourMediaJob%')->orderByDesc('id')->limit(3)->get();
echo "failed ProcessTourMediaJob: " . $ff->count() . "\n";
foreach ($ff as $f) echo "  {$f->failed_at}: " . substr($f->exception,0,200) . "\n";

echo "\n=== recent GS sync logs ===\n";
foreach (DB::table('sync_logs')->where('wholesaler_id',9)->orderByDesc('id')->limit(5)
    ->get(['id','status','sync_type','tours_updated','started_at','completed_at']) as $l) {
    echo "  #{$l->id} {$l->status} {$l->sync_type} upd={$l->tours_updated} {$l->started_at}->{$l->completed_at}\n";
}

echo "\n=== sample GS tour: current pdf_url + hash ===\n";
foreach (DB::table('tours')->where('wholesaler_id',9)->whereNotNull('pdf_url')->orderByDesc('updated_at')->limit(5)
    ->get(['id','wholesaler_tour_code','pdf_url','pdf_branding_hash','updated_at']) as $t) {
    $host = parse_url($t->pdf_url, PHP_URL_HOST);
    echo "  #{$t->id} {$t->wholesaler_tour_code} host={$host} hash=".($t->pdf_branding_hash??'NULL')." upd={$t->updated_at}\n";
}
