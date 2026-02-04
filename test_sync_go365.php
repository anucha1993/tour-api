<?php
/**
 * Test GO365 Sync with nested path support
 * Run: php test_sync_go365.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\SyncToursJob;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;

echo "=== Testing GO365 Sync with Nested Path Support ===\n\n";

// Get GO365 config
$config = WholesalerApiConfig::with('wholesaler')
    ->where('wholesaler_id', 6)
    ->first();

if (!$config) {
    echo "❌ GO365 config not found!\n";
    exit(1);
}

echo "Wholesaler: {$config->wholesaler->name}\n";
echo "API URL: {$config->api_base_url}\n";

// Check aggregation_config
$aggConfig = $config->aggregation_config;
echo "\nAggregation Config:\n";
print_r($aggConfig);

// Check field mappings
$mappings = WholesalerFieldMapping::where('wholesaler_id', 6)->get();
echo "\nField Mappings: " . $mappings->count() . "\n";
if ($mappings->count() === 0) {
    echo "⚠️  WARNING: No field mappings found for GO365!\n";
    echo "Please configure mappings at /dashboard/integrations/6/mapping\n";
}

// Show some mappings
foreach ($mappings->take(10) as $m) {
    echo "  - {$m->section_name}.{$m->our_field} => {$m->their_field}\n";
}

echo "\n--- Running Sync (limit 1 tour) ---\n\n";

try {
    // Create and run sync job with limit
    // Constructor: wholesalerId, transformedData, syncType, limit
    $job = new SyncToursJob(6, null, 'incremental', 1);
    $job->handle();
    
    echo "\n✅ Sync completed!\n";
    
    // Check result
    $tour = \App\Models\Tour::where('wholesaler_id', 6)
        ->orderBy('updated_at', 'desc')
        ->first();
    
    if ($tour) {
        echo "\n=== Latest Synced Tour ===\n";
        echo "ID: {$tour->id}\n";
        echo "Title: {$tour->title}\n";
        echo "Code: {$tour->tour_code}\n";
        
        $periods = \App\Models\Period::where('tour_id', $tour->id)->count();
        echo "Periods: {$periods}\n";
    }
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
