<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tours = App\Models\Tour::where('wholesaler_id', 14)->withCount('periods')->get(['id', 'title', 'wholesaler_tour_code']);
echo "=== Tours for wholesaler_id=14 ===\n";
foreach ($tours as $t) {
    $icon = $t->periods_count > 0 ? '✅' : '❌';
    echo "{$icon} id={$t->id} | {$t->wholesaler_tour_code} | periods={$t->periods_count} | " . mb_substr($t->title, 0, 50) . "\n";
}
echo "\nTotal tours: " . $tours->count() . "\n";
echo "Tours with periods: " . $tours->where('periods_count', '>', 0)->count() . "\n";
echo "Tours without periods: " . $tours->where('periods_count', 0)->count() . "\n";
