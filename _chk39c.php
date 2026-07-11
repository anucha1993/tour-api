<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== pending jobs by queue ===\n";
foreach (DB::table('jobs')->select('queue', DB::raw('count(*) as c'))->groupBy('queue')->get() as $r) {
    echo "  {$r->queue}: {$r->c}\n";
}

echo "\n=== failed_jobs (media / ProcessTourMedia) recent ===\n";
$failed = DB::table('failed_jobs')
    ->where('payload','like','%ProcessTourMediaJob%')
    ->orderByDesc('failed_at')->limit(5)->get();
echo "count=".$failed->count()."\n";
foreach ($failed as $f) {
    echo "  {$f->failed_at} :: " . substr($f->exception,0,300) . "\n\n";
}

echo "\n=== recent sync logs wholesaler 9 (GS) ===\n";
$logs = DB::table('sync_logs')->where('wholesaler_id',9)->orderByDesc('id')->limit(8)
    ->get(['id','status','sync_type','created_items','updated_items','started_at','finished_at']);
foreach ($logs as $l) {
    echo "  #{$l->id} {$l->status} type={$l->sync_type} created={$l->created_items} updated={$l->updated_items} start={$l->started_at} end={$l->finished_at}\n";
}
