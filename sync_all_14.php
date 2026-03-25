<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SyncLog;
use App\Models\WholesalerApiConfig;
use App\Jobs\SyncToursJob;
use Illuminate\Support\Facades\Cache;

$config = WholesalerApiConfig::find(13);
$wId = $config->wholesaler_id;

// Release any stale lock
Cache::lock("sync_lock:wholesaler:{$wId}")->forceRelease();

// Clear stuck logs
SyncLog::where('wholesaler_id', $wId)->where('status', 'running')->update([
    'status' => 'failed', 'completed_at' => now(),
]);

echo "Running SyncToursJob (limit=ALL) synchronously...\n";
$job = new SyncToursJob(
    wholesalerId: $wId,
    transformedData: null,
    syncType: 'manual',
    limit: null  // sync all 9 tours
);
$job->handle();
echo "Done!\n\n";

// Check result
$log = SyncLog::where('wholesaler_id', $wId)->orderByDesc('started_at')->first();
echo "SyncLog:\n";
echo "  status         : {$log->status}\n";
echo "  tours_processed: {$log->tours_processed}\n";
echo "  tours_created  : {$log->tours_created}\n";
echo "  tours_updated  : {$log->tours_updated}\n";
echo "  error_count    : {$log->error_count}\n";
if ($log->error_message) {
    echo "  error_message  : " . substr($log->error_message, 0, 300) . "\n";
}

// Check periods
$tours = App\Models\Tour::where('wholesaler_id', $wId)->withCount('periods')->get(['id', 'wholesaler_tour_code', 'title']);
echo "\nPeriods per tour:\n";
foreach ($tours as $t) {
    $icon = $t->periods_count > 0 ? '✅' : '❌';
    echo "  {$icon} {$t->wholesaler_tour_code} → periods={$t->periods_count}\n";
}
