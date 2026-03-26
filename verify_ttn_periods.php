<?php
/**
 * Verify: TTN Japan periods — DB vs API comparison
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = \App\Models\WholesalerApiConfig::find(22);
$wholesalerId = $config->wholesaler_id;

// Get all tours for this wholesaler
$tours = \App\Models\Tour::where('wholesaler_id', $wholesalerId)->get();
echo "=== Tours in DB (wholesaler_id={$wholesalerId}) ===\n";
echo "Total tours: " . $tours->count() . "\n\n";

$totalDbPeriods = 0;
foreach ($tours as $t) {
    $periods = \App\Models\Period::where('tour_id', $t->id)->get();
    $totalDbPeriods += $periods->count();
    echo "📦 [{$t->id}] {$t->tour_code} (ext_id={$t->external_id})\n";
    echo "   {$t->title}\n";
    echo "   min_price=" . number_format($t->min_price ?? 0) . " price_adult=" . number_format($t->price_adult ?? 0) . "\n";
    echo "   DB periods: " . $periods->count() . "\n";
    
    foreach ($periods as $p) {
        $offer = \App\Models\Offer::where('period_id', $p->id)->first();
        echo "     [{$p->id}] {$p->start_date} → {$p->end_date}  status={$p->status}  cap={$p->capacity}  avail={$p->available}\n";
        if ($offer) {
            echo "       price_adult=" . number_format($offer->price_adult ?? 0) 
                . " price_single=" . number_format($offer->price_single ?? 0)
                . " price_infant=" . number_format($offer->price_infant ?? 0)
                . " price_joinland=" . number_format($offer->price_joinland ?? 0)
                . " commission=" . number_format($offer->commission_agent ?? 0) . "\n";
        } else {
            echo "       ❌ No offer found\n";
        }
    }
    echo "\n";
}

echo "Total DB periods: {$totalDbPeriods}\n\n";

// Now compare with raw API data
echo "=== Comparing with API data ===\n";
$http = new \GuzzleHttp\Client(['timeout' => 30, 'verify' => false]);
$baseUrl = 'https://online.ttnconnect.com/api/agency';

// Get all program IDs
$listResp = $http->get($baseUrl . '/get-programId');
$programs = json_decode($listResp->getBody()->getContents(), true);
echo "API programs: " . count($programs) . "\n\n";

$today = date('Y-m-d');
$totalApiPeriods = 0;
$toursWithPeriods = 0;

// Only check the 5 synced tours
foreach ($tours as $t) {
    $pId = $t->external_id;
    if (!$pId) continue;
    
    try {
        $resp = $http->get($baseUrl . '/program/period/' . $pId);
        $rawPeriods = json_decode($resp->getBody()->getContents(), true);
    } catch (\Throwable $e) {
        echo "  ❌ HTTP error for P_ID={$pId}: {$e->getMessage()}\n";
        continue;
    }
    
    $rawPeriods = is_array($rawPeriods) ? $rawPeriods : [];
    $futurePeriods = array_filter($rawPeriods, fn($rp) => ($rp['P_DUE_START'] ?? '') >= $today);
    
    $dbPeriods = \App\Models\Period::where('tour_id', $t->id)->count();
    
    echo "📦 {$t->tour_code} (ext_id={$pId})\n";
    echo "   API total periods: " . count($rawPeriods) . "\n";
    echo "   API future periods: " . count($futurePeriods) . "\n";
    echo "   DB periods: {$dbPeriods}\n";
    
    if (count($futurePeriods) > 0) {
        $toursWithPeriods++;
    }
    $totalApiPeriods += count($futurePeriods);
    
    // Show API future periods detail
    foreach ($futurePeriods as $i => $fp) {
        $start = $fp['P_DUE_START'] ?? '?';
        $end   = $fp['P_DUE_END'] ?? '?';
        $prices = $fp['Price'] ?? [];
        
        // Find best Open price
        $bestOpen = null;
        foreach ($prices as $price) {
            if (($price['P_STATUS'] ?? '') === 'Open') {
                if (!$bestOpen || (float)($price['P_ADULT_PRICE'] ?? 0) < (float)($bestOpen['P_ADULT_PRICE'] ?? 0)) {
                    $bestOpen = $price;
                }
            }
        }
        // Find cheapest price overall
        $cheapest = null;
        foreach ($prices as $price) {
            $pa = (float)($price['P_ADULT_PRICE'] ?? 0);
            if ($pa > 0 && (!$cheapest || $pa < (float)($cheapest['P_ADULT_PRICE'] ?? 0))) {
                $cheapest = $price;
            }
        }
        
        $best = $bestOpen ?? $cheapest;
        if (!$best) continue;
        
        echo "   API[{$i}] {$start} → {$end}" 
            . "  status=" . ($best['P_STATUS'] ?? '?')
            . "  vol=" . ($best['P_VOLUME'] ?? '?')
            . "  avail=" . ($best['P_AVAILABLE'] ?? '?')
            . "  ฿" . number_format((float)($best['P_ADULT_PRICE'] ?? 0))
            . "  (" . count($prices) . " price tiers)\n";
    }
    echo "\n";
}

echo "\n=== SUMMARY ===\n";
echo "Tours synced: " . $tours->count() . "\n";
echo "API total future periods: {$totalApiPeriods}\n";
echo "DB total periods: {$totalDbPeriods}\n";
echo "Match: " . ($totalApiPeriods == $totalDbPeriods ? "✅ YES" : "❌ NO ({$totalApiPeriods} vs {$totalDbPeriods})") . "\n";
