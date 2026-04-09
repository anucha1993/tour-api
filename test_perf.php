<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::enableQueryLog();
$start = microtime(true);

// Step 1: Get setting
$setting = App\Models\InternationalTourSetting::active()->with('countryCovers')->orderBy('sort_order')->first();
$countryId = App\Models\Country::where('slug', 'china')->value('id');
echo "Country ID: $countryId\n";
echo "Step 1 (setting+country): " . round((microtime(true)-$start)*1000) . "ms\n";

// Step 2: Base query + country filter + count
$q = $setting->getBaseQuery();
$q->where('primary_country_id', $countryId);
$t2 = microtime(true);
$total = (clone $q)->count();
echo "Tour count for china: $total | count query: " . round((microtime(true)-$t2)*1000) . "ms\n";

// Step 3: Paginate (just IDs)
$t3 = microtime(true);
$ids = (clone $q)->orderByRaw('COALESCE(view_count, 0) DESC')->orderBy('created_at', 'desc')->limit(10)->pluck('id');
echo "IDs: " . $ids->implode(',') . " | id query: " . round((microtime(true)-$t3)*1000) . "ms\n";

// Step 4: Full paginate with eager loads
$t4 = microtime(true);
$tours = $setting->getTours(10, ['country_id' => $countryId]);
echo "Full getTours: " . round((microtime(true)-$t4)*1000) . "ms | items: " . count($tours->items()) . "\n";

// Step 5: Format
$controller = new App\Http\Controllers\PublicTourController();
$t5 = microtime(true);
$formatted = collect($tours->items())->map(function ($tour) use ($setting, $controller) {
    $ref = new ReflectionMethod($controller, 'formatTourListItem');
    $ref->setAccessible(true);
    return $ref->invoke($controller, $tour, $setting);
});
echo "Format: " . round((microtime(true)-$t5)*1000) . "ms\n";

echo "\nTotal: " . round((microtime(true)-$start)*1000) . "ms\n";

// Show queries
$queries = DB::getQueryLog();
echo "\nTotal queries: " . count($queries) . "\n";
$slowQueries = collect($queries)->filter(fn($q) => $q['time'] > 100)->sortByDesc('time');
echo "Slow queries (>100ms):\n";
foreach ($slowQueries as $sq) {
    echo "  " . round($sq['time']) . "ms: " . substr($sq['query'], 0, 200) . "\n";
}
