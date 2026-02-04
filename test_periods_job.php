<?php
/**
 * Test SyncPeriodsJob directly
 */

// Force sync queue for immediate processing
putenv('QUEUE_CONNECTION=sync');

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Override queue config to sync
config(['queue.default' => 'sync']);

use App\Jobs\SyncToursJob;
use App\Jobs\SyncPeriodsJob;
use App\Models\Tour;
use App\Models\WholesalerApiConfig;

echo "=== Testing SyncPeriodsJob Directly ===\n\n";

$tour = Tour::where('wholesaler_id', 6)->orderBy('id', 'desc')->first();
if (!$tour) {
    echo "No GO365 tour found!\n";
    exit(1);
}

echo "Tour ID: {$tour->id}\n";
echo "Title: {$tour->title}\n";
echo "External ID: {$tour->external_id}\n\n";

echo "--- Calling SyncPeriodsJob directly ---\n";

try {
    $job = new SyncPeriodsJob($tour->id, $tour->external_id, 6);
    $job->handle();
    echo "\n✅ SyncPeriodsJob completed!\n";
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}

// Check periods
$periods = \App\Models\Period::where('tour_id', $tour->id)
    ->with('offer')
    ->orderBy('start_date')
    ->get();
    
echo "\nPeriods: " . $periods->count() . "\n";
foreach ($periods->take(5) as $p) {
    $price = $p->offer ? $p->offer->price_adult : 'N/A';
    echo "  - {$p->start_date} -> {$p->end_date} (Price: {$price})\n";
}
