<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Jobs\SyncPeriodsJob;
use Illuminate\Support\Facades\Log;

$config = WholesalerApiConfig::find(20); // integration_id = 20

$noPeriodTours = Tour::where('wholesaler_id', 35)
    ->doesntHave('periods')
    ->get(['id', 'tour_code', 'external_id']);

echo "Dispatching SyncPeriodsJob for " . $noPeriodTours->count() . " tours...\n";

foreach ($noPeriodTours as $tour) {
    $externalId = $tour->external_id ?? $tour->tour_code;
    echo "  Tour #{$tour->id} ({$externalId})... ";
    
    try {
        // Run synchronously instead of dispatching to queue
        SyncPeriodsJob::dispatchSync(
            $tour->id,
            $externalId,
            $config->id
        );
        
        // Check if periods were created
        $count = \App\Models\Period::where('tour_id', $tour->id)->count();
        echo "✅ {$count} periods\n";
    } catch (\Exception $e) {
        echo "❌ " . $e->getMessage() . "\n";
    }
}

echo "\n=== After sync ===\n";
$totalPeriods = \App\Models\Period::whereHas('tour', fn($q) => $q->where('wholesaler_id', 35))->count();
$toursWithPeriods = Tour::where('wholesaler_id', 35)->has('periods')->count();
echo "Tours with periods: {$toursWithPeriods}/145\n";
echo "Total periods: {$totalPeriods}\n";
