<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;
use App\Models\DomesticTourSetting;
use App\Models\Country;

echo "=== Domestic Tour Debug ===\n\n";

// 1. Find Thailand
$thailand = Country::where('name_en', 'like', '%Thai%')->orWhere('name_th', 'like', '%ไทย%')->first();
echo "Thailand: " . ($thailand ? "ID={$thailand->id}, name_en={$thailand->name_en}" : "NOT FOUND") . "\n";
echo "THAILAND_ID constant: " . DomesticTourSetting::THAILAND_ID . "\n\n";

// 2. Check tours with primary_country_id = THAILAND_ID
$thaiId = DomesticTourSetting::THAILAND_ID;
$tours = Tour::where('primary_country_id', $thaiId)->get();
echo "Tours with primary_country_id={$thaiId}: " . $tours->count() . "\n";
foreach ($tours as $t) {
    echo "  [{$t->id}] {$t->tour_code} - {$t->title} (status={$t->status})\n";
    $periods = $t->periods()->get();
    echo "    Periods: " . $periods->count() . "\n";
    foreach ($periods->take(3) as $p) {
        echo "      start={$p->start_date}, status={$p->status}, is_visible=" . ($p->is_visible ? 'Y' : 'N') . "\n";
    }
}

// 3. Check all countries to verify Thailand ID
echo "\nAll countries around ID 8:\n";
$countries = Country::whereIn('id', [7, 8, 9])->get();
foreach ($countries as $c) {
    echo "  ID={$c->id}, name_en={$c->name_en}\n";
}

// 4. Also try to find any tour that has Thailand via countries pivot
echo "\nTours via countries pivot (Thai):\n";
if ($thailand) {
    $pivotTours = Tour::whereHas('countries', function($q) use ($thailand) {
        $q->where('countries.id', $thailand->id);
    })->get();
    echo "  Count: " . $pivotTours->count() . "\n";
    foreach ($pivotTours as $t) {
        echo "  [{$t->id}] {$t->tour_code} (primary_country_id={$t->primary_country_id}, status={$t->status})\n";
    }
}

// 5. Settings
$settingsCount = DomesticTourSetting::count();
echo "\nDomesticTourSettings count: $settingsCount\n";

// 6. Try base query without period filter
echo "\nBase query WITHOUT period filter:\n";
$noPeriodsQuery = Tour::where('status', 'active')->where('primary_country_id', $thaiId)->get();
echo "  Count: " . $noPeriodsQuery->count() . "\n";

echo "\nBase query WITH period filter (start_date >= " . now()->toDateString() . "):\n";
$withPeriodsQuery = Tour::where('status', 'active')
    ->where('primary_country_id', $thaiId)
    ->whereHas('periods', function($q) {
        $q->where('start_date', '>=', now()->toDateString())->where('status', 'open');
    })
    ->get();
echo "  Count: " . $withPeriodsQuery->count() . "\n";

