<?php

/**
 * Debug Integration 13 Sync Script
 * Usage: php debug_integration_13.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Models\SyncLog;
use App\Models\SyncErrorLog;
use App\Jobs\SyncToursJob;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

$INTEGRATION_ID = 13;

echo "=====================================\n";
echo " DEBUG: Integration #{$INTEGRATION_ID} Sync\n";
echo " Date: " . now()->toDateTimeString() . "\n";
echo "=====================================\n\n";

// ─── STEP 1: Config ─────────────────────
echo "── STEP 1: WholesalerApiConfig ──\n";
$config = WholesalerApiConfig::with('wholesaler')->find($INTEGRATION_ID);

if (!$config) {
    echo "❌ ไม่พบ WholesalerApiConfig id={$INTEGRATION_ID}\n\n";
    echo "   รายการทั้งหมดที่มี:\n";
    foreach (WholesalerApiConfig::all() as $c) {
        $wName = $c->wholesaler?->name ?? "wholesaler_id:{$c->wholesaler_id}";
        echo "   - id={$c->id} | {$wName} | {$c->api_base_url}\n";
    }
    exit(1);
}

echo "✅ พบ config:\n";
echo "   id              : {$config->id}\n";
echo "   wholesaler_id   : {$config->wholesaler_id}\n";
echo "   wholesaler_name : " . ($config->wholesaler?->name ?? 'N/A') . "\n";
echo "   api_base_url    : {$config->api_base_url}\n";
echo "   api_format      : {$config->api_format}\n";
echo "   auth_type       : {$config->auth_type}\n";
echo "   sync_enabled    : " . ($config->sync_enabled ? '✅ YES' : '❌ NO') . "\n";
echo "   sync_mode       : " . ($config->sync_mode ?? 'single') . "\n";
echo "   sync_limit      : " . ($config->sync_limit ?? 'unlimited') . "\n";
echo "   is_active       : " . ($config->is_active ? '✅ YES' : '❌ NO') . "\n";
echo "   health_check    : " . ($config->last_health_check_status ? '✅ OK' : '❌ FAIL') . "\n";
echo "   last_health_at  : " . ($config->last_health_check_at ?? 'never') . "\n";

// ─── STEP 2: Auth Credentials & Endpoints ───
echo "\n── STEP 2: Auth Credentials & Endpoints ──\n";
$credentials = $config->auth_credentials ?? [];
echo "   credential keys : " . (empty($credentials) ? '(empty!)' : implode(', ', array_keys($credentials))) . "\n";

$token = $credentials['token'] ?? $credentials['api_key'] ?? $credentials['bearer_token'] ?? null;
if ($token) {
    echo "   token/key       : " . substr($token, 0, 10) . "...(length:" . strlen($token) . ")\n";
} else {
    echo "   ⚠️ token/api_key : NOT FOUND in credentials\n";
}

$endpoints = $credentials['endpoints'] ?? [];
if (empty($endpoints)) {
    echo "   ⚠️ endpoints     : EMPTY — ไม่มี endpoints config\n";
} else {
    echo "   endpoints:\n";
    foreach ($endpoints as $k => $v) {
        echo "     - {$k}: {$v}\n";
    }
}

$pagination = $credentials['pagination'] ?? [];
echo "   pagination      : " . (empty($pagination) ? '(none)' : json_encode($pagination)) . "\n";

// ─── STEP 3: Aggregation Config ─────────
echo "\n── STEP 3: Aggregation Config ──\n";
$aggConfig = $config->aggregation_config ?? [];
if (empty($aggConfig)) {
    echo "   ⚠️ aggregation_config: EMPTY\n";
} else {
    echo json_encode($aggConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// ─── STEP 4: Field Mappings ─────────────
echo "\n── STEP 4: Field Mappings ──\n";
$mappings = WholesalerFieldMapping::where('wholesaler_id', $config->wholesaler_id)
    ->where('is_active', true)
    ->get()
    ->groupBy('section_name');

if ($mappings->isEmpty()) {
    echo "   ⚠️ ไม่พบ field mappings (is_active=true) สำหรับ wholesaler_id={$config->wholesaler_id}\n";
} else {
    foreach ($mappings as $section => $items) {
        echo "   [{$section}] {$items->count()} fields\n";
        foreach ($items as $m) {
            $path = $m->their_field_path ?? $m->their_field ?? '-';
            echo "     {$m->our_field} ← {$path}\n";
        }
    }
}

// ─── STEP 5: Cache Lock ─────────────────
echo "\n── STEP 5: Cache / Distributed Lock ──\n";
$lockKey = "sync_lock:wholesaler:{$config->wholesaler_id}";
$lock = Cache::lock($lockKey, 1);
$canAcquire = $lock->get();
if ($canAcquire) {
    $lock->forceRelease();
    echo "   ✅ Lock '{$lockKey}' = FREE\n";
} else {
    echo "   ❌ Lock '{$lockKey}' = LOCKED (sync กำลังรันอยู่หรือค้างอยู่!)\n";
}

// Running sync logs
$running = SyncLog::where('wholesaler_id', $config->wholesaler_id)
    ->where('status', 'running')->get();
if ($running->isNotEmpty()) {
    echo "   ⚠️ มี SyncLog ที่ status=running:\n";
    foreach ($running as $r) {
        $age = now()->diffForHumans($r->started_at);
        echo "     id={$r->id} started={$r->started_at} ({$age}) heartbeat={$r->last_heartbeat_at}\n";
    }
}

// ─── STEP 6: Recent SyncLog ─────────────
echo "\n── STEP 6: Recent SyncLogs (last 5) ──\n";
$logs = SyncLog::where('wholesaler_id', $config->wholesaler_id)
    ->orderByDesc('started_at')
    ->limit(5)
    ->get();

if ($logs->isEmpty()) {
    echo "   (ไม่เคย sync)\n";
} else {
    foreach ($logs as $l) {
        $statusIcon = match($l->status) {
            'completed' => '✅',
            'failed'    => '❌',
            'running'   => '🔄',
            default     => '⚪',
        };
        echo "   {$statusIcon} id={$l->id} | {$l->status} | tours:{$l->tours_processed} | errors:{$l->error_count} | {$l->started_at}\n";
        if ($l->error_message) {
            echo "      ↳ " . substr($l->error_message, 0, 200) . "\n";
        }
    }
}

// ─── STEP 7: Recent SyncErrorLogs ───────
echo "\n── STEP 7: Recent SyncErrorLogs (last 10) ──\n";
$errors = SyncErrorLog::where('wholesaler_id', $config->wholesaler_id)
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();

if ($errors->isEmpty()) {
    echo "   (ไม่มี error log)\n";
} else {
    foreach ($errors as $e) {
        echo "   [{$e->error_type}] {$e->created_at}\n";
        echo "   ↳ " . substr($e->error_message, 0, 200) . "\n";
        if ($e->tour_code) echo "   ↳ tour_code: {$e->tour_code}\n";
        echo "\n";
    }
}

// ─── STEP 8: Failed Jobs ────────────────
echo "── STEP 8: Failed Jobs ──\n";
$failed = DB::table('failed_jobs')
    ->orderByDesc('failed_at')
    ->take(5)
    ->get();

$hasRelatedFail = false;
foreach ($failed as $f) {
    $payload = json_decode($f->payload, true);
    $jobClass = $payload['displayName'] ?? 'Unknown';
    if (str_contains($jobClass, 'Sync')) {
        $hasRelatedFail = true;
        $errorLine = explode("\n", $f->exception)[0] ?? '';
        echo "   ❌ {$jobClass} at {$f->failed_at}\n";
        echo "      " . substr($errorLine, 0, 250) . "\n\n";
    }
}
if (!$hasRelatedFail) {
    echo "   (ไม่มี Sync failed jobs)\n";
}

// ─── STEP 9: Create Adapter ─────────────
echo "\n── STEP 9: Create Adapter ──\n";
try {
    $adapter = AdapterFactory::create($config->wholesaler_id);
    echo "✅ Adapter: " . get_class($adapter) . "\n";
    echo "   fetchPeriods()    : " . (method_exists($adapter, 'fetchPeriods') ? '✅' : '❌ ไม่มี') . "\n";
    echo "   fetchItineraries(): " . (method_exists($adapter, 'fetchItineraries') ? '✅' : '❌ ไม่มี') . "\n";
} catch (\Throwable $e) {
    echo "❌ ไม่สามารถสร้าง Adapter: {$e->getMessage()}\n";
    echo "   " . $e->getFile() . ":{$e->getLine()}\n";
    exit(1);
}

// ─── STEP 10: Test Fetch Tours From API ─
echo "\n── STEP 10: Test Fetch Tours From API ──\n";
try {
    echo "   กำลัง fetch tours (cursor=null)...\n";
    $result = $adapter->fetchTours(null);

    echo "   success     : " . ($result->success ? '✅ true' : '❌ false') . "\n";
    echo "   errorMessage: " . ($result->errorMessage ?? 'none') . "\n";
    echo "   tours count : " . count($result->tours ?? []) . "\n";
    echo "   hasMore     : " . ($result->hasMore ? 'yes' : 'no') . "\n";
    echo "   nextCursor  : " . ($result->nextCursor ?? 'null') . "\n";

    if (!empty($result->tours)) {
        $firstTour = $result->tours[0];
        echo "\n   First tour keys: " . implode(', ', array_keys($firstTour)) . "\n";

        // Detect periods/departures keys
        $periodKeys = ['Periods', 'periods', 'Schedules', 'schedules', 'Departures', 'departures'];
        foreach ($periodKeys as $key) {
            if (isset($firstTour[$key]) && is_array($firstTour[$key])) {
                echo "   Periods key '{$key}': " . count($firstTour[$key]) . " items\n";
                if (!empty($firstTour[$key][0])) {
                    echo "   First period keys: " . implode(', ', array_keys($firstTour[$key][0])) . "\n";
                }
                break;
            }
        }

        // Check aggregation_config data structure
        $dataStructure = $aggConfig['data_structure'] ?? [];
        if (!empty($dataStructure['departures']['path'] ?? null)) {
            $path = $dataStructure['departures']['path'];
            echo "\n   Aggregation departures path: {$path}\n";
            $segments = preg_split('/\[\]\.?/', rtrim($path, '[]'));
            $segments = array_filter($segments, fn($s) => !empty(trim($s)));
            $current = [$firstTour];
            foreach ($segments as $seg) {
                $newItems = [];
                foreach ($current as $item) {
                    if (isset($item[$seg]) && is_array($item[$seg])) {
                        foreach ($item[$seg] as $n) {
                            if (is_array($n)) $newItems[] = $n;
                        }
                    }
                }
                $current = $newItems;
                echo "     → '{$seg}': " . count($current) . " items\n";
            }
            if (!empty($current[0])) {
                echo "     first nested keys: " . implode(', ', array_keys($current[0])) . "\n";
            }
        }
    } elseif (!$result->success) {
        echo "\n   ❌ API ไม่ตอบกลับสำเร็จ — ตรวจสอบ credentials/endpoint\n";
    }
} catch (\Throwable $e) {
    echo "❌ ERROR fetchTours: {$e->getMessage()}\n";
    echo "   File: " . $e->getFile() . ":{$e->getLine()}\n";
    echo "   Trace (3 frames):\n";
    $frames = array_slice(explode("\n", $e->getTraceAsString()), 0, 6);
    foreach ($frames as $frame) echo "   {$frame}\n";
}

// ─── STEP 11: Run Sync (limit=1) ────────
echo "\n── STEP 11: Run SyncToursJob synchronously (limit=1) ──\n";
echo "   ⚠️ กำลังรัน sync จริง (limit=1, ไม่ผ่าน queue)...\n\n";

// Release any stale lock first
Cache::lock("sync_lock:wholesaler:{$config->wholesaler_id}")->forceRelease();

try {
    $job = new SyncToursJob(
        wholesalerId: $config->wholesaler_id,
        transformedData: null,
        syncType: 'manual',
        limit: 1
    );
    $job->handle();
    echo "\n✅ SyncToursJob completed without exception\n";
} catch (\Throwable $e) {
    echo "\n❌ SyncToursJob threw exception:\n";
    echo "   " . get_class($e) . ": {$e->getMessage()}\n";
    echo "   File: " . $e->getFile() . ":{$e->getLine()}\n";
    echo "\n   Stack trace:\n";
    $frames = array_slice(explode("\n", $e->getTraceAsString()), 0, 10);
    foreach ($frames as $frame) echo "   {$frame}\n";
}

// ─── STEP 12: SyncLog result after run ──
echo "\n── STEP 12: SyncLog after run ──\n";
$lastLog = SyncLog::where('wholesaler_id', $config->wholesaler_id)
    ->orderByDesc('started_at')
    ->first();
if ($lastLog) {
    echo "   Last log id    : {$lastLog->id}\n";
    echo "   Status         : {$lastLog->status}\n";
    echo "   tours_processed: {$lastLog->tours_processed}\n";
    echo "   tours_created  : {$lastLog->tours_created}\n";
    echo "   tours_updated  : {$lastLog->tours_updated}\n";
    echo "   tours_skipped  : {$lastLog->tours_skipped}\n";
    echo "   error_count    : {$lastLog->error_count}\n";
    echo "   started_at     : {$lastLog->started_at}\n";
    echo "   completed_at   : " . ($lastLog->completed_at ?? 'null') . "\n";
    if ($lastLog->error_message) {
        echo "   error_message  : " . substr($lastLog->error_message, 0, 500) . "\n";
    }
}

// ─── STEP 13: New SyncErrorLogs after run ───
echo "\n── STEP 13: New SyncErrors after run ──\n";
$newErrors = SyncErrorLog::where('wholesaler_id', $config->wholesaler_id)
    ->where('created_at', '>=', now()->subMinutes(5))
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();
if ($newErrors->isEmpty()) {
    echo "   ✅ ไม่มี error ใหม่\n";
} else {
    foreach ($newErrors as $e) {
        echo "   ❌ [{$e->error_type}] " . substr($e->error_message, 0, 200) . "\n";
        if ($e->tour_code) echo "      tour: {$e->tour_code}\n";
    }
}

echo "\n=====================================\n";
echo " DONE\n";
echo "=====================================\n";
