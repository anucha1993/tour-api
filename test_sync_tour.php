<?php
/**
 * Test Sync เจาะจง tour NT202603004 เท่านั้น
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Support\Facades\Cache;

$tourCode = 'NT202603004';
$tour = Tour::where('tour_code', $tourCode)->first();

echo "=== Before Sync ===\n";
$beforePeriods = Period::where('tour_id', $tour->id)->get();
$beforeOpen = $beforePeriods->where('status', 'open')->count();
$beforeClosed = $beforePeriods->where('status', 'closed')->count();
$beforeSoldOut = $beforePeriods->where('status', 'sold_out')->count();
echo "Total: {$beforePeriods->count()} | Open: {$beforeOpen} | Closed: {$beforeClosed} | Sold Out: {$beforeSoldOut}\n\n";

// Fetch from API
echo "=== Fetching from API ===\n";
$config = WholesalerApiConfig::where('wholesaler_id', $tour->wholesaler_id)->first();
$adapter = AdapterFactory::create($tour->wholesaler_id);
$result = $adapter->fetchTours(null);

// Find this tour's data
$apiTour = null;
foreach ($result->tours as $t) {
    if (($t['ProductID'] ?? null) == $tour->external_id) {
        $apiTour = $t;
        break;
    }
}

if (!$apiTour) {
    echo "Tour not found in API\n";
    exit(1);
}

$apiPeriods = $apiTour['Periods'] ?? [];
echo "API Periods: " . count($apiPeriods) . "\n";

// Get field mappings
$mappings = WholesalerFieldMapping::where('wholesaler_id', $config->wholesaler_id)
    ->where('is_active', true)
    ->get()
    ->groupBy('section_name');

// Manually build transformed data for just this tour
// (same as what SyncToursJob::fetchAndMapTours would produce)
echo "\n=== Running SyncToursJob for this tour only ===\n";

// Clear locks
\App\Models\SyncLog::where('wholesaler_id', $config->wholesaler_id)
    ->where('status', 'running')
    ->update(['status' => 'failed', 'completed_at' => now()]);
Cache::lock("sync_lock:wholesaler:{$config->wholesaler_id}")->forceRelease();

// Run full sync with limit=1, but we need to ensure this tour gets synced
// Instead, let's directly call processSingleTour via reflection or create the transformed data

// Use SyncToursJob to transform and process
$job = new \App\Jobs\SyncToursJob(
    wholesalerId: $config->wholesaler_id,
    transformedData: null,
    syncType: 'full',
    limit: 200  // enough to include our tour
);

$job->handle();

echo "\n=== After Sync ===\n";
$afterPeriods = Period::where('tour_id', $tour->id)->get();
$afterOpen = $afterPeriods->where('status', 'open')->count();
$afterClosed = $afterPeriods->where('status', 'closed')->count();
$afterSoldOut = $afterPeriods->where('status', 'sold_out')->count();
echo "Total: {$afterPeriods->count()} | Open: {$afterOpen} | Closed: {$afterClosed} | Sold Out: {$afterSoldOut}\n\n";

// Show orphan periods
$orphans = $afterPeriods->filter(fn($p) => $p->status === 'closed');
if ($orphans->isNotEmpty()) {
    echo "Closed (orphan) periods:\n";
    foreach ($orphans as $p) {
        echo "  - ExtID: {$p->external_id}, Start: {$p->start_date->format('Y-m-d')}, Status: {$p->status}\n";
    }
}

// Show sold_out
$soldOut = $afterPeriods->filter(fn($p) => $p->status === 'sold_out');
if ($soldOut->isNotEmpty()) {
    echo "\nSold Out periods:\n";
    foreach ($soldOut as $p) {
        echo "  - ExtID: {$p->external_id}, Start: {$p->start_date->format('Y-m-d')}, Cap: {$p->getRawOriginal('capacity')}, Booked: {$p->getRawOriginal('booked')}, Avail: {$p->getRawOriginal('available')}\n";
    }
}

echo "\nDone.\n";
