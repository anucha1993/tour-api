<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check a specific period's actual DB data
$period = App\Models\Period::where('tour_id', 3354)
    ->orderBy('start_date')
    ->first();

if ($period) {
    echo "Period #{$period->id}:" . PHP_EOL;
    echo "  start_date: " . $period->start_date . PHP_EOL;
    echo "  end_date: " . $period->end_date . PHP_EOL;
    echo "  external_id: " . $period->external_id . PHP_EOL;
    echo "  price_adult: " . var_export($period->price_adult, true) . PHP_EOL;
    echo "  price_child: " . var_export($period->price_child, true) . PHP_EOL;
    echo "  price_child_nobed: " . var_export($period->price_child_nobed, true) . PHP_EOL;
    echo "  price_single: " . var_export($period->price_single, true) . PHP_EOL;
    echo "  capacity: " . var_export($period->capacity, true) . PHP_EOL;
    echo "  available: " . var_export($period->available, true) . PHP_EOL;
    echo "  booked: " . var_export($period->booked, true) . PHP_EOL;
    echo "  commission_agent: " . var_export($period->commission_agent, true) . PHP_EOL;
    echo "  commission_sale: " . var_export($period->commission_sale, true) . PHP_EOL;
    echo "  status: " . $period->status . PHP_EOL;
    
    // Check raw DB values
    $raw = DB::table('periods')->where('id', $period->id)->first();
    echo PHP_EOL . "  RAW DB:" . PHP_EOL;
    echo "  price_adult: " . var_export($raw->price_adult, true) . PHP_EOL;
    echo "  commission_agent: " . var_export($raw->commission_agent, true) . PHP_EOL;
}

// Also check the sync log details
$log = App\Models\SyncLog::where('wholesaler_id', 57)->orderBy('id', 'desc')->first();
echo PHP_EOL . "Sync Log details:" . PHP_EOL;
echo "  total_received: " . var_export($log->total_received, true) . PHP_EOL;
echo "  total_created: " . var_export($log->total_created, true) . PHP_EOL;
echo "  total_updated: " . var_export($log->total_updated, true) . PHP_EOL;
echo "  total_skipped: " . var_export($log->total_skipped, true) . PHP_EOL;
echo "  periods_received: " . var_export($log->periods_received ?? null, true) . PHP_EOL;
echo "  periods_created: " . var_export($log->periods_created ?? null, true) . PHP_EOL;

// Also get a count of total tours with periods
$toursWithPeriods = App\Models\Tour::where('wholesaler_id', 57)
    ->withCount('periods')
    ->get();
echo PHP_EOL . "Tours breakdown:" . PHP_EOL;
foreach ($toursWithPeriods as $t) {
    echo "  {$t->tour_code} ({$t->external_id}): {$t->periods_count} periods" . PHP_EOL;
}
