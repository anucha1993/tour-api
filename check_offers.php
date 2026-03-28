<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check offers for tour 3354 (จางเจียเจี้ย)
$periodIds = DB::table('periods')->where('tour_id', 3354)->pluck('id');
echo "Periods for tour 3354: " . $periodIds->count() . PHP_EOL;

$offers = DB::table('offers')->whereIn('period_id', $periodIds)->get();
echo "Offers count: " . $offers->count() . PHP_EOL;

foreach ($offers->take(3) as $o) {
    echo "  Period {$o->period_id}: adult={$o->price_adult}, child={$o->price_child}, single={$o->price_single}" . PHP_EOL;
    echo "    comm_agent={$o->commission_agent}, comm_sale={$o->commission_sale}" . PHP_EOL;
}

// Also check tour 3353 (ยุโรปตะวันออก) 
$periodIds2 = DB::table('periods')->where('tour_id', 3353)->pluck('id');
echo PHP_EOL . "Periods for tour 3353: " . $periodIds2->count() . PHP_EOL;
$offers2 = DB::table('offers')->whereIn('period_id', $periodIds2)->get();
echo "Offers count: " . $offers2->count() . PHP_EOL;
foreach ($offers2->take(3) as $o) {
    echo "  Period {$o->period_id}: adult={$o->price_adult}, child={$o->price_child}, single={$o->price_single}" . PHP_EOL;
    echo "    comm_agent={$o->commission_agent}, comm_sale={$o->commission_sale}" . PHP_EOL;
}

// Check if price_adult is in the Offer model fillable
$offer = new App\Models\Offer();
echo PHP_EOL . "Offer fillable: " . implode(', ', $offer->getFillable()) . PHP_EOL;
