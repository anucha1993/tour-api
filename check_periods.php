<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Models\Offer;
use App\Services\WholesalerAdapters\AdapterFactory;

$tourCode = 'NT202603004';
$tour = Tour::where('tour_code', $tourCode)->first();

if (!$tour) {
    echo "Tour {$tourCode} not found\n";
    exit(1);
}

echo "=== Tour Info ===\n";
echo "Tour Code: {$tour->tour_code}\n";
echo "Title: {$tour->title}\n";
echo "External ID: {$tour->external_id}\n";
echo "Wholesaler Tour Code: {$tour->wholesaler_tour_code}\n";
echo "Wholesaler ID: {$tour->wholesaler_id}\n\n";

// ─── ข้อมูลใน DB ───
echo "=== Periods in DB (total: " . Period::where('tour_id', $tour->id)->count() . ") ===\n";
$periods = Period::where('tour_id', $tour->id)->orderBy('start_date')->get();

echo str_pad('Start', 13) . str_pad('End', 13) . str_pad('Cap', 6) . str_pad('Book', 6) . str_pad('Avail', 7) . str_pad('Status', 12) . str_pad('ExtID', 12) . str_pad('Price', 10) . "\n";
echo str_repeat('-', 79) . "\n";

foreach ($periods as $p) {
    $offer = Offer::where('period_id', $p->id)->first();
    $price = $offer ? number_format($offer->price_adult) : '-';
    
    echo str_pad($p->start_date->format('Y-m-d'), 13)
       . str_pad($p->end_date ? $p->end_date->format('Y-m-d') : '-', 13)
       . str_pad($p->getRawOriginal('capacity'), 6)
       . str_pad($p->getRawOriginal('booked'), 6)
       . str_pad($p->getRawOriginal('available'), 7)
       . str_pad($p->status, 12)
       . str_pad($p->external_id ?? '-', 12)
       . str_pad($price, 10)
       . "\n";
}

// ─── ข้อมูลจาก API ───
echo "\n=== Periods from API ===\n";

$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', $tour->wholesaler_id)->first();
$adapter = AdapterFactory::create($tour->wholesaler_id);
$result = $adapter->fetchTours(null);

if (!$result->success) {
    echo "Failed to fetch tours: {$result->errorMessage}\n";
    exit(1);
}

// หา tour ที่ตรงกับ external_id
$apiTour = null;
foreach ($result->tours as $t) {
    $productId = $t['ProductID'] ?? $t['product_id'] ?? null;
    $productCode = $t['ProductCode'] ?? $t['product_code'] ?? null;
    
    if ($productId == $tour->external_id || $productCode == $tour->wholesaler_tour_code) {
        $apiTour = $t;
        break;
    }
}

if (!$apiTour) {
    echo "Tour not found in API response (external_id: {$tour->external_id}, code: {$tour->wholesaler_tour_code})\n";
    exit(1);
}

echo "API ProductCode: " . ($apiTour['ProductCode'] ?? '-') . "\n";
echo "API ProductName: " . ($apiTour['ProductName'] ?? '-') . "\n";

$apiPeriods = $apiTour['Periods'] ?? $apiTour['periods'] ?? [];
echo "API Periods count: " . count($apiPeriods) . "\n\n";

echo str_pad('Start', 13) . str_pad('End', 13) . str_pad('GrpSize', 8) . str_pad('Book', 6) . str_pad('Seat', 6) . str_pad('Status', 10) . str_pad('PeriodID', 12) . str_pad('Price', 10) . "\n";
echo str_repeat('-', 78) . "\n";

$futureCount = 0;
$pastCount = 0;
$today = date('Y-m-d');

foreach ($apiPeriods as $ap) {
    $startDate = $ap['PeriodStartDate'] ?? '-';
    $endDate = $ap['PeriodEndDate'] ?? '-';
    $groupSize = $ap['GroupSize'] ?? '-';
    $book = $ap['Book'] ?? '-';
    $seat = $ap['Seat'] ?? '-';
    $status = $ap['PeriodStatus'] ?? '-';
    $periodId = $ap['PeriodID'] ?? '-';
    $price = isset($ap['Price']) ? number_format($ap['Price']) : '-';
    
    $isPast = strtotime($startDate) < strtotime($today);
    if ($isPast) {
        $pastCount++;
    } else {
        $futureCount++;
    }
    
    $marker = $isPast ? ' [PAST]' : '';
    // Flag negative seats
    $seatFlag = (is_numeric($seat) && $seat < 0) ? ' ⚠️' : '';
    
    echo str_pad($startDate, 13)
       . str_pad($endDate, 13)
       . str_pad($groupSize, 8)
       . str_pad($book, 6)
       . str_pad($seat . $seatFlag, 6)
       . str_pad($status, 10)
       . str_pad($periodId, 12)
       . str_pad($price, 10)
       . $marker
       . "\n";
}

echo "\nAPI: {$futureCount} future + {$pastCount} past = " . count($apiPeriods) . " total\n";
echo "DB:  " . $periods->count() . " periods\n";

// เปรียบเทียบ
echo "\n=== Comparison ===\n";
$dbExternalIds = $periods->pluck('external_id')->filter()->toArray();
$apiExternalIds = array_map(fn($p) => (string)($p['PeriodID'] ?? ''), $apiPeriods);

$inApiNotDb = array_diff($apiExternalIds, $dbExternalIds);
$inDbNotApi = array_diff($dbExternalIds, $apiExternalIds);

if (!empty($inApiNotDb)) {
    echo "⚠️ In API but NOT in DB (" . count($inApiNotDb) . "):\n";
    foreach ($inApiNotDb as $id) {
        // find in API data
        foreach ($apiPeriods as $ap) {
            if (($ap['PeriodID'] ?? '') == $id) {
                $startDate = $ap['PeriodStartDate'] ?? '-';
                $seat = $ap['Seat'] ?? '-';
                echo "   PeriodID={$id}, Start={$startDate}, Seat={$seat}\n";
                break;
            }
        }
    }
}

if (!empty($inDbNotApi)) {
    echo "⚠️ In DB but NOT in API (" . count($inDbNotApi) . "):\n";
    foreach ($inDbNotApi as $id) {
        echo "   ExtID={$id}\n";
    }
}

if (empty($inApiNotDb) && empty($inDbNotApi)) {
    echo "✅ All periods match between API and DB\n";
}

echo "\nDone.\n";
