<?php
/**
 * Debug: ทดสอบ cleanupOrphanPeriods โดยตรง
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;

$tourCode = 'NT202603004';
$tour = Tour::where('tour_code', $tourCode)->first();
$config = WholesalerApiConfig::where('wholesaler_id', $tour->wholesaler_id)->first();

echo "Tour: {$tour->tour_code} (ID: {$tour->id})\n";
echo "Config past_period_handling: " . ($config->past_period_handling ?? 'null (default=close)') . "\n\n";

// จำลอง synced external IDs (ที่ API ส่งมา 22 ตัว)
$adapter = AdapterFactory::create($tour->wholesaler_id);
$result = $adapter->fetchTours(null);

$apiTour = null;
foreach ($result->tours as $t) {
    if (($t['ProductID'] ?? null) == $tour->external_id) {
        $apiTour = $t;
        break;
    }
}

$apiPeriods = $apiTour['Periods'] ?? [];
$syncedExternalIds = array_map(fn($p) => (string)($p['PeriodID'] ?? ''), $apiPeriods);

echo "API PeriodIDs (" . count($syncedExternalIds) . "): " . implode(', ', $syncedExternalIds) . "\n\n";

// Check DB periods
$allDbPeriods = Period::where('tour_id', $tour->id)->get();
echo "DB periods (" . $allDbPeriods->count() . "):\n";
foreach ($allDbPeriods as $p) {
    $inApi = in_array((string)$p->external_id, $syncedExternalIds);
    $marker = $inApi ? '✅' : '❌ NOT IN API';
    echo "  ExtID={$p->external_id}, Start={$p->start_date->format('Y-m-d')}, Status={$p->status}, SyncLocked=" . ($p->sync_locked ? 'Y' : 'N') . " {$marker}\n";
}

// Find orphans
echo "\n=== Orphan Detection ===\n";
$orphanPeriods = Period::where('tour_id', $tour->id)
    ->whereNotNull('external_id')
    ->whereNotIn('external_id', $syncedExternalIds)
    ->where('status', '!=', 'closed')
    ->where('status', '!=', 'cancelled')
    ->where('sync_locked', false)
    ->get();

echo "Orphan periods found: {$orphanPeriods->count()}\n";
foreach ($orphanPeriods as $o) {
    echo "  ExtID={$o->external_id}, Start={$o->start_date->format('Y-m-d')}, Status={$o->status}\n";
}

// Now actually close them
if ($orphanPeriods->isNotEmpty()) {
    echo "\n=== Closing orphan periods ===\n";
    foreach ($orphanPeriods as $orphan) {
        echo "  Closing ExtID={$orphan->external_id}...\n";
        $orphan->update(['status' => 'closed']);
    }
    echo "✅ Done closing {$orphanPeriods->count()} orphan periods\n";
}

// Verify
echo "\n=== After Cleanup ===\n";
$afterPeriods = Period::where('tour_id', $tour->id)->get();
$open = $afterPeriods->where('status', 'open')->count();
$closed = $afterPeriods->where('status', 'closed')->count();
$soldOut = $afterPeriods->where('status', 'sold_out')->count();
echo "Total: {$afterPeriods->count()} | Open: {$open} | Closed: {$closed} | Sold Out: {$soldOut}\n";
