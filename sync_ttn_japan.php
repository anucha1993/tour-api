<?php
/**
 * Quick sync: TTN Japan integration 22, limit 5 tours to test DB save
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = \App\Models\WholesalerApiConfig::find(22);
if (!$config) { echo "Integration 22 not found\n"; exit(1); }

$limit = (int) ($argv[1] ?? 0);
$limitLabel = $limit > 0 ? "limit {$limit}" : "ALL";
echo "=== Dispatching SyncToursJob for integration 22 ({$limitLabel}) ===\n";

// Set sync limit temporarily
$originalLimit = $config->sync_limit;
$config->sync_limit = $limit ?: null;
$config->save();

try {
    $job = new \App\Jobs\SyncToursJob($config->wholesaler_id);
    $job->handle();
    echo "✅ Sync completed\n";
} catch (\Throwable $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Restore original limit
    $config->sync_limit = $originalLimit;
    $config->save();
}

// Check results
$tours = \App\Models\Tour::where('wholesaler_id', $config->wholesaler_id)
    ->orderBy('id', 'desc')
    ->limit(30)
    ->get(['id', 'tour_code', 'title', 'min_price', 'price_adult', 'transport_id', 'cover_image_url']);

echo "\n=== Tours in DB (wholesaler_id={$config->wholesaler_id}) ===\n";
foreach ($tours as $t) {
    echo "  [{$t->id}] {$t->tour_code} - " . mb_substr($t->title ?? '', 0, 50) . "\n";
    echo "       min_price=" . number_format($t->min_price ?? 0) 
        . " price_adult=" . number_format($t->price_adult ?? 0)
        . " transport={$t->transport_id}"
        . " cover=" . ($t->cover_image_url ? 'YES' : 'NO') . "\n";
    
    $periods = \App\Models\Period::where('tour_id', $t->id)->count();
    echo "       periods={$periods}\n";
}
