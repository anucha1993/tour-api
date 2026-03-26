<?php
/**
 * Debug: Check ALL TTN Japan programs — how many periods does the API return?
 * Goal: Understand why most tours have 0 periods
 */
require __DIR__ . '/vendor/autoload.php';

$http = new \GuzzleHttp\Client(['timeout' => 30, 'verify' => false]);
$baseUrl = 'https://online.ttnconnect.com/api/agency';

function apiGet(string $url): ?array {
    global $http;
    try {
        $resp = $http->get($url);
        return json_decode($resp->getBody()->getContents(), true);
    } catch (\Throwable $e) {
        return null;
    }
}

$today = date('Y-m-d');
echo "Today: {$today}\n\n";

// Phase 1: Get all program IDs
$list = apiGet($baseUrl . '/get-programId');
if (!is_array($list)) { echo "Cannot fetch program list\n"; exit(1); }
echo "Total programs: " . count($list) . "\n\n";

$stats = ['total' => 0, 'with_periods' => 0, 'with_future' => 0, 'total_periods' => 0, 'total_future' => 0, 'api_error' => 0];
$details = [];

foreach ($list as $i => $p) {
    $pid = $p['P_ID'] ?? null;
    if (!$pid) continue;
    $stats['total']++;

    // Phase 2: Tour detail (just for name)
    $tour = apiGet($baseUrl . '/program/' . $pid);
    $code = '?';
    $name = '?';
    $tourPrice = '?';
    if (is_array($tour) && !empty($tour)) {
        $t = $tour[0];
        $code = $t['P_CODE'] ?? '?';
        $name = mb_substr($t['P_NAME'] ?? '?', 0, 50);
        $tourPrice = $t['P_PRICE'] ?? '?';
    }

    // Phase 3: Periods
    $periods = apiGet($baseUrl . '/program/period/' . $pid);
    if (!is_array($periods)) {
        $stats['api_error']++;
        echo sprintf("[%3d] P_ID=%-4s %-15s %-50s  ❌ API ERROR\n", $i+1, $pid, $code, $name);
        continue;
    }

    $totalPeriods = count($periods);
    $futurePeriods = 0;
    $pastPeriods = 0;
    $futureOpen = 0;
    $futureSoldOut = 0;
    $earliestFuture = null;
    $latestPast = null;

    foreach ($periods as $per) {
        $start = $per['P_DUE_START'] ?? '';
        $isFuture = ($start >= $today);
        if ($isFuture) {
            $futurePeriods++;
            if (!$earliestFuture || $start < $earliestFuture) $earliestFuture = $start;
            // Check if any price tier is "Open"
            $hasOpen = false;
            foreach ($per['Price'] ?? [] as $price) {
                if (($price['P_STATUS'] ?? '') === 'Open') $hasOpen = true;
            }
            if ($hasOpen) $futureOpen++;
            else $futureSoldOut++;
        } else {
            $pastPeriods++;
            if (!$latestPast || $start > $latestPast) $latestPast = $start;
        }
    }

    if ($totalPeriods > 0) $stats['with_periods']++;
    if ($futurePeriods > 0) $stats['with_future']++;
    $stats['total_periods'] += $totalPeriods;
    $stats['total_future'] += $futurePeriods;

    $periodInfo = $totalPeriods === 0
        ? "0 periods"
        : "{$totalPeriods} periods ({$pastPeriods} past, {$futurePeriods} future [{$futureOpen} open, {$futureSoldOut} sold-out])";

    $dateInfo = '';
    if ($latestPast) $dateInfo .= " lastPast={$latestPast}";
    if ($earliestFuture) $dateInfo .= " nextFuture={$earliestFuture}";

    $icon = $futurePeriods > 0 ? '✅' : ($totalPeriods > 0 ? '⏰' : '⬜');

    echo sprintf("[%3d] P_ID=%-4s %-15s  %s  %s%s  P_PRICE=%s\n",
        $i+1, $pid, $code, $icon, $periodInfo, $dateInfo, $tourPrice);
}

echo "\n=== SUMMARY ===\n";
echo "Total programs: {$stats['total']}\n";
echo "With any periods: {$stats['with_periods']}\n";
echo "With future periods: {$stats['with_future']}\n";
echo "Without any periods: " . ($stats['total'] - $stats['with_periods']) . "\n";
echo "Total period records: {$stats['total_periods']}\n";
echo "Total future periods: {$stats['total_future']}\n";
echo "API errors: {$stats['api_error']}\n";
