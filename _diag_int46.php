<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;

$i = WholesalerApiConfig::find(46);
if (!$i) { echo "WholesalerApiConfig 46 not found\n"; exit; }

echo "=== Integration (WholesalerApiConfig) 46 ===\n";
echo "Name: {$i->name}\n";
echo "Wholesaler: {$i->wholesaler_id}\n";
echo "Type: ".($i->integration_type ?? 'n/a')."\n";
echo "Last sync: ".($i->last_sync_at ?? 'never')."\n\n";

echo "=== Tours ===\n";
echo "Tours by wholesaler_id={$i->wholesaler_id}: ".Tour::where('wholesaler_id', $i->wholesaler_id)->count()."\n";
echo "Total tours in DB: ".Tour::count()."\n";

// Check soft delete
$hasSoftDelete = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Tour::class) ?: []);
echo "SoftDelete on Tour: ".($hasSoftDelete ? 'yes' : 'NO')."\n";

// Check by status
echo "\n=== By status ===\n";
foreach (['active','inactive','draft'] as $s) {
    echo "$s: ".Tour::where('status',$s)->count()."\n";
}

// Latest activity
echo "\n=== Recent tours (last 10 updated) ===\n";
foreach (Tour::orderByDesc('updated_at')->limit(10)->get(['id','tour_code','title','status','wholesaler_id','updated_at']) as $t) {
    echo sprintf("id=%d ws=%s status=%s code=%s upd=%s\n", $t->id, $t->wholesaler_id, $t->status, $t->tour_code, $t->updated_at);
}

// Latest sync logs
echo "\n=== Recent sync activity for integration 46 ===\n";
try {
    $logs = DB::table('sync_logs')->where('integration_id', 46)->orderByDesc('id')->limit(5)->get();
    foreach ($logs as $l) {
        echo "log #{$l->id} status={$l->status} started={$l->started_at} finished={$l->finished_at}\n";
        if ($l->summary) echo "  summary: ".substr((string)$l->summary,0,200)."\n";
    }
} catch (\Throwable $e) { echo "no sync_logs table: ".$e->getMessage()."\n"; }
