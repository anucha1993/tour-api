<?php
/**
 * Debug: scan all 53 TTN Japan programs for periods
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$http = new \GuzzleHttp\Client(['timeout' => 30, 'verify' => false]);
$baseUrl = 'https://online.ttnconnect.com/api/agency';

function apiGet(string $url) {
    global $http;
    try {
        $resp = $http->get($url);
        $body = $resp->getBody()->getContents();
        return ['data' => json_decode($body, true), 'raw_length' => strlen($body)];
    } catch (\Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

// Phase 1: get all IDs
$list = apiGet($baseUrl . '/get-programId');
$programs = $list['data'] ?? [];
echo "Total programs: " . count($programs) . "\n\n";

// Check raw response of period endpoint for first program
$firstPId = $programs[0]['P_ID'] ?? null;
echo "=== Raw period response for P_ID={$firstPId} ===\n";
$rawResp = $http->get($baseUrl . '/program/period/' . $firstPId);
$rawBody = $rawResp->getBody()->getContents();
echo "Status: " . $rawResp->getStatusCode() . "\n";
echo "Content-Type: " . $rawResp->getHeaderLine('Content-Type') . "\n";
echo "Body length: " . strlen($rawBody) . "\n";
echo "Body (first 500 chars): " . substr($rawBody, 0, 500) . "\n\n";

// Also check the tour detail — maybe P_DEPARTURE has period data?
echo "=== Checking tour detail for period data in P_DEPARTURE etc ===\n";
$detail = apiGet($baseUrl . '/program/' . $firstPId);
$tour = ($detail['data'][0] ?? []);
echo "P_DEPARTURE type: " . gettype($tour['P_DEPARTURE'] ?? null) . "\n";
echo "P_DEPARTURE: " . json_encode($tour['P_DEPARTURE'] ?? null) . "\n";
echo "P_RETURN type: " . gettype($tour['P_RETURN'] ?? null) . "\n";
echo "P_RETURN: " . json_encode($tour['P_RETURN'] ?? null) . "\n";
echo "P_PRICE: " . json_encode($tour['P_PRICE'] ?? null) . "\n\n";

// Check if Itinerary has data
$itinerary = $tour['Itinerary'] ?? null;
if (is_array($itinerary)) {
    echo "Itinerary: " . count($itinerary) . " items\n";
    if (!empty($itinerary)) {
        echo "  First item keys: " . implode(', ', array_keys($itinerary[0])) . "\n";
        echo "  First item: " . json_encode($itinerary[0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "Itinerary: " . var_export($itinerary, true) . "\n";
}

echo "\n=== Scanning all programs for periods ===\n";
$withPeriods = 0;
$withoutPeriods = 0;
$samplePeriod = null;

// Check first 10 for speed
foreach (array_slice($programs, 0, 10) as $i => $prog) {
    $pId = $prog['P_ID'] ?? null;
    $result = apiGet($baseUrl . '/program/period/' . $pId);
    $periods = $result['data'] ?? [];
    $count = is_array($periods) ? count($periods) : 0;
    
    if ($count > 0) {
        $withPeriods++;
        if (!$samplePeriod) {
            $samplePeriod = ['p_id' => $pId, 'periods' => $periods];
        }
        echo "  P_ID={$pId}: {$count} periods ✅\n";
    } else {
        $withoutPeriods++;
        echo "  P_ID={$pId}: 0 periods (raw_len={$result['raw_length']})\n";
    }
}

echo "\nWith periods: {$withPeriods}, Without: {$withoutPeriods}\n";

if ($samplePeriod) {
    echo "\n=== Sample period from P_ID={$samplePeriod['p_id']} ===\n";
    $per = $samplePeriod['periods'][0] ?? [];
    echo json_encode($per, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
