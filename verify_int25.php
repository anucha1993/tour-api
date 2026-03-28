<?php
/**
 * Comprehensive check of integration 25 sync results
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Models\Offer;
use App\Models\SyncLog;

// 1. Sync Log
$log = SyncLog::where('wholesaler_id', 57)->orderBy('id', 'desc')->first();
echo "=== Sync Log #{$log->id} ===" . PHP_EOL;
echo "Status: {$log->status}" . PHP_EOL;
echo "Tours received: {$log->tours_received}" . PHP_EOL;
echo "Created: {$log->total_created} | Updated: {$log->total_updated} | Skipped: {$log->total_skipped} | Errors: {$log->total_errors}" . PHP_EOL;
echo "Duration: {$log->duration_seconds}s" . PHP_EOL;

// 2. Tour Details
$tours = Tour::where('wholesaler_id', 57)->orderBy('id')->get();
echo PHP_EOL . "=== {$tours->count()} Tours ===" . PHP_EOL;

$totalPeriods = 0;
$totalOffers = 0;
$issues = [];

foreach ($tours as $i => $t) {
    $periods = Period::where('tour_id', $t->id)->get();
    $futurePeriods = $periods->where('start_date', '>=', now()->toDateString());
    $periodIds = $periods->pluck('id');
    $offers = Offer::whereIn('period_id', $periodIds)->get();
    $countries = $t->countries()->get();

    $totalPeriods += $periods->count();
    $totalOffers += $offers->count();

    echo PHP_EOL . ($i+1) . ". {$t->tour_code} | {$t->wholesaler_tour_code}" . PHP_EOL;
    echo "   Title: " . mb_substr($t->title, 0, 70) . PHP_EOL;

    // Check: duration
    $durOk = $t->duration_days > 0;
    echo "   Duration: {$t->duration_days}D/{$t->duration_nights}N " . ($durOk ? "OK" : "MISSING") . PHP_EOL;
    if (!$durOk) $issues[] = "{$t->tour_code}: duration=0";

    // Check: transport
    $transOk = !empty($t->transport_id);
    echo "   Transport: " . ($t->transport_id ?? 'null') . " " . ($transOk ? "OK" : "MISSING") . PHP_EOL;
    if (!$transOk) $issues[] = "{$t->tour_code}: no transport_id";

    // Check: countries
    $countryNames = $countries->pluck('name_th')->toArray();
    echo "   Countries: " . ($countries->isEmpty() ? 'NONE' : implode(', ', $countryNames)) . PHP_EOL;

    // Check: cover image
    $coverOk = !empty($t->cover_image_url);
    echo "   Cover: " . ($coverOk ? mb_substr($t->cover_image_url, 0, 50) . '...' : 'MISSING') . PHP_EOL;

    // Check: periods
    echo "   Periods: {$periods->count()} total, {$futurePeriods->count()} future" . PHP_EOL;

    // Check: offers (should match periods)
    $offersMatch = $offers->count() === $periods->count();
    echo "   Offers: {$offers->count()} " . ($offersMatch ? "OK" : "MISMATCH (periods={$periods->count()})") . PHP_EOL;
    if (!$offersMatch) $issues[] = "{$t->tour_code}: offers({$offers->count()}) != periods({$periods->count()})";

    // Check first offer pricing
    if ($offers->isNotEmpty()) {
        $o = $offers->first();
        $priceOk = $o->price_adult > 0;
        echo "   Sample: adult={$o->price_adult}, child={$o->price_child}, single={$o->price_single}" . PHP_EOL;
        echo "           comm_agent={$o->commission_agent}, comm_sale={$o->commission_sale}" . PHP_EOL;
        if (!$priceOk) $issues[] = "{$t->tour_code}: price_adult=0";
    }

    // Check first period dates
    if ($periods->isNotEmpty()) {
        $p = $periods->sortBy('start_date')->first();
        $dateOk = preg_match('/^\d{4}-\d{2}-\d{2}/', $p->start_date);
        echo "   First period: {$p->start_date} - {$p->end_date} (cap={$p->capacity}, avail={$p->available}, booked={$p->booked})" . PHP_EOL;
        if (!$dateOk) $issues[] = "{$t->tour_code}: bad date format {$p->start_date}";
    }
}

// 3. Summary
echo PHP_EOL . "=== Summary ===" . PHP_EOL;
echo "Tours: {$tours->count()}" . PHP_EOL;
echo "Total periods: {$totalPeriods}" . PHP_EOL;
echo "Total offers: {$totalOffers}" . PHP_EOL;

if (empty($issues)) {
    echo PHP_EOL . "ALL CHECKS PASSED!" . PHP_EOL;
} else {
    echo PHP_EOL . "ISSUES FOUND:" . PHP_EOL;
    foreach ($issues as $issue) {
        echo "  - {$issue}" . PHP_EOL;
    }
}
