<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Delete old tours with 0 periods (from previous test sync without skip logic)
$oldTours = \App\Models\Tour::where('wholesaler_id', 40)
    ->whereDoesntHave('periods')
    ->get(['id', 'tour_code', 'title']);

echo "Tours with 0 periods to delete: " . $oldTours->count() . "\n";
foreach ($oldTours as $t) {
    echo "  Deleting [{$t->id}] {$t->tour_code} - " . mb_substr($t->title, 0, 40) . "\n";
    // Delete related periods and their offers first
    $periodIds = \App\Models\Period::where('tour_id', $t->id)->pluck('id');
    if ($periodIds->isNotEmpty()) {
        \App\Models\Offer::whereIn('period_id', $periodIds)->delete();
        \App\Models\Period::where('tour_id', $t->id)->delete();
    }
    $t->delete();
}

// Final count
$remaining = \App\Models\Tour::where('wholesaler_id', 40)->count();
$totalPeriods = \App\Models\Period::whereHas('tour', fn($q) => $q->where('wholesaler_id', 40))->count();
echo "\nRemaining tours: {$remaining}\n";
echo "Total periods: {$totalPeriods}\n";
