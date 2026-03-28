<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Look for exact tour NT202603352
echo "=== Looking for tour NT202603352 ===\n";
$tour = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('tour_code', 'NT202603352')
    ->first();
if (!$tour) {
    $tour = DB::table('tours')
        ->where('wholesaler_id', 6)
        ->where('title', 'like', '%ROMANTIC ROAD%')
        ->first();
}
if ($tour) {
    echo "Found: ID:{$tour->id} code:{$tour->tour_code} wcode:{$tour->wholesaler_tour_code}\n";
    echo "Title: " . mb_substr($tour->title, 0, 120) . "\n";
    echo "Status: {$tour->status}\n";
}

// Check the value_map transform for period_visible -> status
echo "\n=== Value map config for status mapping ===\n";
$statusMapping = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('our_field', 'status')
    ->where('section_name', 'departure')
    ->first();
if ($statusMapping) {
    echo "their_field: {$statusMapping->their_field}\n";
    echo "transform_type: {$statusMapping->transform_type}\n";
    echo "transform_config: {$statusMapping->transform_config}\n";
    echo "default_value: {$statusMapping->default_value}\n";
}

// Check is_visible value for integration 6 periods
echo "\n=== is_visible distribution ===\n";
$tourIds = DB::table('tours')->where('wholesaler_id', 6)->pluck('id');
$vis = DB::table('periods')
    ->whereIn('tour_id', $tourIds)
    ->select('is_visible', DB::raw('count(*) as cnt'))
    ->groupBy('is_visible')
    ->get();
foreach ($vis as $v) {
    echo "  is_visible=" . ($v->is_visible === null ? 'NULL' : $v->is_visible) . ": {$v->cnt}\n";
}

// Check sale_status
echo "\n=== sale_status mapping ===\n";
$saleMapping = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('our_field', 'sale_status')
    ->first();
if ($saleMapping) {
    echo "Found: {$saleMapping->their_field} -> sale_status\n";
} else {
    echo "No sale_status mapping found\n";
}

// Check is_visible mapping
echo "\n=== is_visible mapping ===\n";
$visMapping = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('our_field', 'is_visible')
    ->first();
if ($visMapping) {
    echo "Found: {$visMapping->their_field} -> is_visible\n";
} else {
    echo "No is_visible mapping found\n";
}

// Check raw API sample for a period to see what fields are available
echo "\n=== Check raw period data from outbound API log ===\n";
$apiLog = DB::table('outbound_api_logs')
    ->where('wholesaler_id', 6)
    ->where('request_type', 'fetch_periods')
    ->orderByDesc('id')
    ->first();
if ($apiLog) {
    echo "Found fetch_periods log ID:{$apiLog->id}\n";
    $resp = json_decode($apiLog->response_body ?? '{}', true);
    if ($resp) {
        // Find tour_period data
        $data = $resp['data'] ?? $resp;
        if (isset($data[0])) $data = $data[0];
        $tourPeriod = $data['tour_period'] ?? $data['periods'][0]['tour_period'] ?? null;
        if ($tourPeriod && isset($tourPeriod[0])) {
            echo "Sample period keys: " . implode(', ', array_keys($tourPeriod[0])) . "\n";
            echo "\nFirst period data:\n";
            foreach ($tourPeriod[0] as $k => $v) {
                if (is_array($v)) {
                    echo "  {$k}: " . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
                } else {
                    echo "  {$k}: {$v}\n";
                }
            }
        }
    }
} else {
    echo "No fetch_periods API log found\n";
    // Check what's in outbound_api_logs for integration 6 recently
    $logs = DB::table('outbound_api_logs')
        ->where('wholesaler_id', 6)
        ->orderByDesc('id')
        ->limit(3)
        ->get();
    echo "Recent API logs:\n";
    foreach ($logs as $l) {
        $cols = array_keys((array)$l);
        echo "  ID:{$l->id} type:{$l->request_type} cols: " . implode(',', $cols) . "\n";
    }
}

// Check what raw data SyncPeriodsJob received for a specific tour
echo "\n=== Checking sync error logs for period-related errors ===\n";
$periodErrors = DB::table('sync_error_logs')
    ->where('wholesaler_id', 6)
    ->where('section_name', 'period')
    ->orderByDesc('id')
    ->limit(3)
    ->get();
echo "Period errors: " . $periodErrors->count() . "\n";
foreach ($periodErrors as $e) {
    echo "  {$e->error_type}: " . substr($e->error_message, 0, 150) . "\n";
}

// 14 tours with periods but no offers - list them all
echo "\n=== All 14 tours with periods but NO offers ===\n";
foreach ($tourIds as $tid) {
    $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
    if ($pids->isEmpty()) continue;
    $offerCount = DB::table('offers')->whereIn('period_id', $pids)->count();
    if ($offerCount == 0) {
        $t = DB::table('tours')->where('id', $tid)->first(['id','tour_code','title','created_at']);
        $pc = $pids->count();
        // Check if all periods are past
        $futurePc = DB::table('periods')->whereIn('id', $pids)->where('start_date', '>=', '2026-03-28')->count();
        echo "  ID:{$t->id} {$t->tour_code} | periods:{$pc} future:{$futurePc} | created:{$t->created_at} | " . mb_substr($t->title, 0, 50) . "\n";
    }
}
