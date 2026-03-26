<?php
/**
 * Quick test: iTravel adapter connection + first tour
 * Usage: php test_itravel.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== iTravel Connection Test ===\n\n";

// 1. Check DB config
$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 35)->first();
if (!$config) {
    echo "ERROR: No config found for wholesaler_id=35\n";
    exit(1);
}

echo "DB Config:\n";
echo "  id              : {$config->id}\n";
echo "  integration_type: {$config->integration_type}\n";
echo "  headcode_file   : {$config->headcode_file}\n";
echo "  auth_type       : {$config->auth_type}\n";
$creds = $config->auth_credentials ?? [];
$headers = $creds['headers'] ?? [];
foreach ($headers as $k => $v) {
    $masked = strlen($v) > 8 ? substr($v, 0, 4) . '****' . substr($v, -4) : '****';
    echo "  header [{$k}]  : {$masked}\n";
}
echo "\n";

// 2. Load adapter via AdapterFactory
try {
    $adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(35);
    echo "AdapterFactory: OK — class=" . get_class($adapter) . "\n\n";
} catch (\Throwable $e) {
    echo "AdapterFactory ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Call fetchTours (Phase 1 + Phase 2 for first tour only)
// 3a. Direct HTTP test BEFORE going through the adapter
echo "Direct HTTP test (Phase 1 - program list)...\n";
@ob_flush(); flush();
$directStart = microtime(true);
try {
    $ch = curl_init('https://itravels.center/api/program');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'itravels-secret: ' . ($creds['headers']['itravels-secret'] ?? ''),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $rawBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo "  cURL ERROR: {$curlError}\n";
        exit(1);
    }
    echo "  HTTP status : {$httpCode}\n";
    if ($httpCode !== 200) {
        echo "  Response body: " . substr($rawBody, 0, 500) . "\n";
        exit(1);
    }
    $decoded = json_decode($rawBody, true);
    $tourCount = count($decoded['data'] ?? []);
    echo "  Tours in list : {$tourCount}\n";
    $elapsed = round((microtime(true) - $directStart) * 1000);
    echo "  Elapsed       : {$elapsed} ms\n\n";

    if ($tourCount > 0) {
        // Phase 2: fetch periods for FIRST tour only
        $firstCode = $decoded['data'][0]['code'] ?? null;
        echo "Direct HTTP test (Phase 2 - periods for code={$firstCode})...\n";
        @ob_flush(); flush();
        $p2Start = microtime(true);
        $ch2 = curl_init('https://itravels.center/api/program/' . rawurlencode($firstCode));
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'itravels-secret: ' . ($creds['headers']['itravels-secret'] ?? ''),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $rawBody2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $curlError2 = curl_error($ch2);
        curl_close($ch2);

        if ($curlError2) {
            echo "  Phase 2 cURL ERROR: {$curlError2}\n";
        } else {
            $p2Elapsed = round((microtime(true) - $p2Start) * 1000);
            $decoded2 = json_decode($rawBody2, true);
            $periodCount = count($decoded2['data'] ?? []);
            echo "  HTTP status  : {$httpCode2}\n";
            echo "  Periods      : {$periodCount}\n";
            echo "  Elapsed      : {$p2Elapsed} ms\n";
            if ($httpCode2 !== 200) {
                echo "  Body snippet : " . substr($rawBody2, 0, 300) . "\n";
            }
        }
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "Direct HTTP EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}

// 3b. Test via adapter — __ping__ mode (Phase 1 only, no per-tour period fetches)
//     This is what the UI "ทดสอบการเชื่อมต่อ" button actually calls via fetchSample()
echo "Calling fetchTours('__ping__') via adapter (controller mode)...\n";
@ob_flush(); flush();
$start = microtime(true);

try {
    $result = $adapter->fetchTours('__ping__');
} catch (\Throwable $e) {
    echo "fetchTours(__ping__) EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

$elapsed = round((microtime(true) - $start) * 1000);
echo "  success     : " . ($result->success ? 'true' : 'false') . "\n";
echo "  elapsed     : {$elapsed} ms\n";
echo "  tours count : " . count($result->tours ?? []) . "\n\n";

if (!$result->success) {
    echo "  error: " . ($result->errorMessage ?? '(no message)') . "\n";
    exit(1);
}

// 3c. Also test __sample__ mode (Phase 1 + Phase 2 for 1 tour)
echo "Calling fetchTours('__sample__') via adapter (1 tour with periods)...\n";
@ob_flush(); flush();
$start = microtime(true);

try {
    $result = $adapter->fetchTours('__sample__');
} catch (\Throwable $e) {
    echo "fetchTours(__sample__) EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}

$elapsed = round((microtime(true) - $start) * 1000);
echo "  success : " . ($result->success ? 'true' : 'false') . "\n";
echo "  elapsed : {$elapsed} ms\n";

if (!$result->success) {
    echo "  error   : " . ($result->errorMessage ?? '(no message)') . "\n";
    exit(1);
}

$tours = $result->tours ?? [];
echo "  tours   : " . count($tours) . "\n\n";

if (!empty($tours)) {
    $first = $tours[0];
    echo "First tour sample:\n";
    echo "  code             : " . ($first['code'] ?? '-') . "\n";
    echo "  name             : " . mb_substr($first['name'] ?? '-', 0, 60) . "\n";
    echo "  departure_by_code: " . ($first['departure_by_code'] ?? '-') . "\n";
    echo "  day / night      : " . ($first['day'] ?? '-') . " / " . ($first['night'] ?? '-') . "\n";
    echo "  periods count    : " . count($first['periods'] ?? []) . "\n";

    if (!empty($first['periods'])) {
        $p = $first['periods'][0];
        echo "  period[0]        : id={$p['id']}, start={$p['date_start']}, end={$p['date_end']}, status={$p['status_mapped']}, price1={$p['price1']}\n";
    }
}

echo "\n=== PASS ===\n";
