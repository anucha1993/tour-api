<?php
/**
 * Debug period field extraction
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerFieldMapping;
use App\Models\WholesalerApiConfig;

echo "=== Debug Period Field Extraction ===\n\n";

// Get mappings
$mappings = WholesalerFieldMapping::where('wholesaler_id', 6)
    ->where('section_name', 'departure')
    ->where('is_active', true)
    ->get();

echo "Departure Mappings:\n";
foreach ($mappings as $m) {
    echo "  {$m->our_field} => {$m->their_field}\n";
}

// Get config
$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
$aggConfig = $config->aggregation_config ?? [];
$basePath = $aggConfig['data_structure']['departures']['path'] ?? null;

echo "\nBase Path: {$basePath}\n";

// Simulate cleaning path
echo "\nCleaned Paths:\n";
foreach ($mappings as $m) {
    $fullPath = $m->their_field;
    $cleanedPath = $fullPath;
    
    // Clean logic from SyncPeriodsJob
    if ($basePath) {
        $basePathWithDot = $basePath . '.';
        if (str_starts_with($fullPath, $basePathWithDot)) {
            $cleanedPath = substr($fullPath, strlen($basePathWithDot));
        } else {
            // Try stripping standard prefixes
            $cleanedPath = preg_replace('/^[Pp]eriods\[\]\./', '', $fullPath);
            $cleanedPath = preg_replace('/^tour_period\[\]\./', '', $cleanedPath);
        }
    }
    
    echo "  {$m->our_field}: '{$fullPath}' => '{$cleanedPath}'\n";
}
