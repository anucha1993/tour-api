<?php
/**
 * Debug: see raw TTN Japan API responses
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = \App\Models\WholesalerApiConfig::find(22);
if (!$config) { echo "Integration 22 not found\n"; exit(1); }

require_once storage_path('headcode/ttn_japan.php');

// Use Guzzle directly since httpGetQuiet is protected
$http = new \GuzzleHttp\Client(['timeout' => 30, 'verify' => false]);
$baseUrl = 'https://online.ttnconnect.com/api/agency';

function apiGet(string $url) {
    global $http;
    try {
        $resp = $http->get($url);
        return json_decode($resp->getBody()->getContents(), true);
    } catch (\Throwable $e) {
        echo "HTTP Error: " . $e->getMessage() . "\n";
        return null;
    }
}

echo "=== Phase 1: Program IDs ===\n";
$list = apiGet($baseUrl . '/get-programId');
echo "Count: " . (is_array($list) ? count($list) : 'not array') . "\n";

if (!is_array($list) || empty($list)) {
    echo "No programs. Raw response:\n";
    var_dump($list);
    exit;
}

// Show first 3 program IDs
echo "First 5 IDs:\n";
foreach (array_slice($list, 0, 5) as $p) {
    echo "  P_ID: " . ($p['P_ID'] ?? '?') . "\n";
}

// Take first program and inspect
$firstPId = $list[0]['P_ID'] ?? null;
if (!$firstPId) { echo "No P_ID in first entry\n"; exit(1); }

echo "\n=== Phase 2: Tour detail for P_ID={$firstPId} ===\n";
$detail = apiGet($baseUrl . '/program/' . $firstPId);
if (is_array($detail) && !empty($detail)) {
    $tour = $detail[0] ?? [];
    echo "Keys: " . implode(', ', array_keys($tour)) . "\n";
    echo "P_CODE: " . ($tour['P_CODE'] ?? '?') . "\n";
    echo "P_NAME: " . ($tour['P_NAME'] ?? '?') . "\n";
    echo "P_AIRLINE: " . ($tour['P_AIRLINE'] ?? '?') . "\n";
    echo "P_DAY: " . ($tour['P_DAY'] ?? '?') . "\n";
    echo "P_NIGHT: " . ($tour['P_NIGHT'] ?? '?') . "\n";
    echo "P_HOTEL_STAR: " . ($tour['P_HOTEL_STAR'] ?? '?') . "\n";
    echo "BANNER: " . substr($tour['BANNER'] ?? '-', 0, 80) . "\n";
    echo "PDF: " . substr($tour['PDF'] ?? '-', 0, 80) . "\n";
} else {
    echo "Empty or not array:\n";
    var_dump($detail);
}

echo "\n=== Phase 3: Periods for P_ID={$firstPId} ===\n";
$periods = apiGet($baseUrl . '/program/period/' . $firstPId);
if (is_array($periods)) {
    echo "Total periods: " . count($periods) . "\n";
    $today = date('Y-m-d');
    $future = 0;
    foreach ($periods as $i => $per) {
        if ($i === 0) {
            echo "Keys: " . implode(', ', array_keys($per)) . "\n\n";
        }
        $start = $per['P_DUE_START'] ?? '?';
        $end   = $per['P_DUE_END'] ?? '?';
        $isFuture = ($start >= $today);
        if ($isFuture) $future++;
        
        // Show first 8 periods
        if ($i < 8) {
            echo "  [{$i}] {$start} → {$end} " . ($isFuture ? '(FUTURE)' : '(PAST)') . "\n";
            $prices = $per['Price'] ?? [];
            if (is_array($prices)) {
                foreach ($prices as $pi => $price) {
                    if ($pi === 0 && $i === 0) {
                        echo "       Price keys: " . implode(', ', array_keys($price)) . "\n";
                    }
                    echo "       Price[{$pi}]: P_AVAILABLE=" . ($price['P_AVAILABLE'] ?? '?')
                        . "  P_ADULT_PRICE=" . ($price['P_ADULT_PRICE'] ?? '?')
                        . "  P_SINGLE_PRICE=" . ($price['P_SINGLE_PRICE'] ?? '?')
                        . "  P_VOLUME=" . ($price['P_VOLUME'] ?? '?')
                        . "\n";
                }
            } else {
                echo "       Price: not array or empty\n";
            }
        }
    }
    echo "\nFuture periods: {$future} / " . count($periods) . "\n";
    
    // Collect all unique P_AVAILABLE values
    $allAvail = [];
    foreach ($periods as $per) {
        foreach ($per['Price'] ?? [] as $price) {
            $v = $price['P_AVAILABLE'] ?? 'NULL';
            $allAvail[$v] = ($allAvail[$v] ?? 0) + 1;
        }
    }
    echo "\nAll P_AVAILABLE values across all periods:\n";
    foreach ($allAvail as $val => $cnt) {
        echo "  '{$val}' => {$cnt} occurrences\n";
    }
} else {
    echo "Not array:\n";
    var_dump($periods);
}

// Also check 2nd program
if (count($list) > 1) {
    $secondPId = $list[1]['P_ID'] ?? null;
    if ($secondPId) {
        echo "\n=== Quick check 2nd tour P_ID={$secondPId} ===\n";
        $periods2 = apiGet($baseUrl . '/program/period/' . $secondPId);
        if (is_array($periods2)) {
            $allAvail2 = [];
            foreach ($periods2 as $per) {
                foreach ($per['Price'] ?? [] as $price) {
                    $v = $price['P_AVAILABLE'] ?? 'NULL';
                    $allAvail2[$v] = ($allAvail2[$v] ?? 0) + 1;
                }
            }
            echo "Periods: " . count($periods2) . "\n";
            echo "P_AVAILABLE values:\n";
            foreach ($allAvail2 as $val => $cnt) {
                echo "  '{$val}' => {$cnt}\n";
            }
        }
    }
}
