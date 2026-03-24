<?php

/**
 * Test Sync Script - ทดสอบ SyncToursJob ด้วย integration_id = 1
 * 
 * Usage: php test_sync.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Models\SyncLog;
use App\Models\SyncCursor;
use App\Models\Tour;
use App\Models\Period;
use App\Jobs\SyncToursJob;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

echo "=== Test Sync Script ===\n";
echo "Date: " . now()->toDateTimeString() . "\n\n";

// ─── Step 1: ตรวจสอบ Config ───
echo "── Step 1: ตรวจสอบ WholesalerApiConfig (integration_id = 1) ──\n";
$config = WholesalerApiConfig::find(1);

if (!$config) {
    echo "❌ ERROR: ไม่พบ WholesalerApiConfig id=1\n";
    echo "   ลองค้นหา config ทั้งหมด:\n";
    $allConfigs = WholesalerApiConfig::all();
    foreach ($allConfigs as $c) {
        echo "   - ID: {$c->id}, Wholesaler ID: {$c->wholesaler_id}, URL: {$c->api_base_url}, Enabled: " . ($c->sync_enabled ? 'Yes' : 'No') . "\n";
    }
    exit(1);
}

echo "✅ Config found:\n";
echo "   - ID: {$config->id}\n";
echo "   - Wholesaler ID: {$config->wholesaler_id}\n";
echo "   - API Base URL: {$config->api_base_url}\n";
echo "   - API Format: {$config->api_format}\n";
echo "   - Auth Type: {$config->auth_type}\n";
echo "   - Sync Enabled: " . ($config->sync_enabled ? 'Yes' : 'No') . "\n";
echo "   - Sync Mode: " . ($config->sync_mode ?? 'single') . "\n";
echo "   - Sync Limit: " . ($config->sync_limit ?? 'unlimited') . "\n";

// ─── Step 2: ตรวจสอบ Endpoints ───
echo "\n── Step 2: ตรวจสอบ Endpoints ──\n";
$credentials = $config->auth_credentials ?? [];
$endpoints = $credentials['endpoints'] ?? [];
echo "   - Auth Credentials keys: " . implode(', ', array_keys($credentials)) . "\n";
echo "   - Endpoints:\n";
if (empty($endpoints)) {
    echo "   ⚠️ ไม่พบ endpoints ใน auth_credentials\n";
} else {
    foreach ($endpoints as $key => $value) {
        echo "     - {$key}: {$value}\n";
    }
}

// Pagination config
$pagination = $credentials['pagination'] ?? [];
echo "   - Pagination: " . json_encode($pagination, JSON_UNESCAPED_UNICODE) . "\n";

// Aggregation config
$aggConfig = $config->aggregation_config ?? [];
echo "   - Aggregation Config: " . json_encode($aggConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

// ─── Step 3: ตรวจสอบ Field Mappings ───
echo "\n── Step 3: ตรวจสอบ Field Mappings ──\n";
$mappings = WholesalerFieldMapping::where('wholesaler_id', $config->wholesaler_id)
    ->where('is_active', true)
    ->get()
    ->groupBy('section_name');

foreach ($mappings as $section => $sectionMappings) {
    echo "   Section: {$section} ({$sectionMappings->count()} fields)\n";
    foreach ($sectionMappings as $m) {
        $path = $m->their_field_path ?? $m->their_field ?? '-';
        $transform = $m->transform_type ?? 'direct';
        echo "     - {$m->our_field} ← {$path} (transform: {$transform})\n";
    }
}

// ─── Step 4: ตรวจสอบ Adapter ───
echo "\n── Step 4: ตรวจสอบ Adapter ──\n";
try {
    $adapter = AdapterFactory::create($config->wholesaler_id);
    echo "✅ Adapter created: " . get_class($adapter) . "\n";
    
    // ตรวจสอบ methods ที่มี
    $adapterClass = new ReflectionClass($adapter);
    $methods = $adapterClass->getMethods(\ReflectionMethod::IS_PUBLIC);
    echo "   Available methods:\n";
    foreach ($methods as $method) {
        if (!$method->isConstructor() && $method->getDeclaringClass()->getName() !== 'stdClass') {
            echo "     - {$method->getName()}()\n";
        }
    }
    
    // ตรวจสอบว่ามี fetchPeriods และ fetchItineraries หรือไม่
    $hasFetchPeriods = method_exists($adapter, 'fetchPeriods');
    $hasFetchItineraries = method_exists($adapter, 'fetchItineraries');
    echo "\n   fetchPeriods(): " . ($hasFetchPeriods ? '✅ มี' : '❌ ไม่มี') . "\n";
    echo "   fetchItineraries(): " . ($hasFetchItineraries ? '✅ มี' : '❌ ไม่มี') . "\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR creating adapter: {$e->getMessage()}\n";
}

// ─── Step 5: ทดสอบ Fetch Tours จาก API ───
echo "\n── Step 5: ทดสอบ Fetch Tours จาก API ──\n";
try {
    $adapter = AdapterFactory::create($config->wholesaler_id);
    echo "   Fetching tours (cursor: null)...\n";
    $result = $adapter->fetchTours(null);
    
    echo "   - Success: " . ($result->success ? 'Yes' : 'No') . "\n";
    echo "   - Error: " . ($result->errorMessage ?? 'none') . "\n";
    echo "   - Tours count: " . count($result->tours ?? []) . "\n";
    echo "   - Has more: " . ($result->hasMore ? 'Yes' : 'No') . "\n";
    echo "   - Next cursor: " . ($result->nextCursor ?? 'null') . "\n";
    
    if (!empty($result->tours)) {
        echo "\n   First tour raw data keys: " . implode(', ', array_keys($result->tours[0])) . "\n";
        
        // แสดงตัวอย่าง departures/periods data
        $firstTour = $result->tours[0];
        $periodKeys = ['Periods', 'periods', 'Schedules', 'schedules', 'Departures', 'departures'];
        foreach ($periodKeys as $key) {
            if (isset($firstTour[$key])) {
                $periodsData = $firstTour[$key];
                echo "   Found '{$key}' with " . count($periodsData) . " items\n";
                if (!empty($periodsData[0])) {
                    echo "   First period keys: " . implode(', ', array_keys($periodsData[0])) . "\n";
                }
                break;
            }
        }
        
        // ตรวจสอบ itinerary data
        $itinKeys = ['Itinerary', 'itinerary', 'Itineraries', 'itineraries', 'Days', 'days', 'Programs', 'programs'];
        foreach ($itinKeys as $key) {
            if (isset($firstTour[$key])) {
                $itinData = $firstTour[$key];
                echo "   Found '{$key}' with " . count($itinData) . " items\n";
                break;
            }
        }
        
        // ตรวจสอบ nested data ตาม aggregation_config
        $dataStructure = $aggConfig['data_structure'] ?? [];
        if (!empty($dataStructure)) {
            echo "\n   Checking nested data structure from aggregation_config:\n";
            
            $departuresPath = $dataStructure['departures']['path'] ?? null;
            if ($departuresPath) {
                echo "   - Departures path: {$departuresPath}\n";
                // ลองดึง nested data
                $segments = preg_split('/\[\]\.?/', rtrim($departuresPath, '[]'));
                $segments = array_filter($segments, fn($s) => !empty($s));
                $current = [$firstTour];
                foreach ($segments as $seg) {
                    $newCurrent = [];
                    foreach ($current as $item) {
                        if (isset($item[$seg]) && is_array($item[$seg])) {
                            foreach ($item[$seg] as $nested) {
                                if (is_array($nested)) $newCurrent[] = $nested;
                            }
                        }
                    }
                    $current = $newCurrent;
                    echo "     → After '{$seg}': " . count($current) . " items\n";
                }
                if (!empty($current[0])) {
                    echo "     First nested item keys: " . implode(', ', array_keys($current[0])) . "\n";
                }
            }
            
            $itinerariesPath = $dataStructure['itineraries']['path'] ?? null;
            if ($itinerariesPath) {
                echo "   - Itineraries path: {$itinerariesPath}\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR fetching tours: {$e->getMessage()}\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

// ─── Step 6: ทดสอบ SyncToursJob (synchronous) ───
echo "\n── Step 6: ทดสอบ SyncToursJob (sync, limit=2) ──\n";
echo "   ⚠️ จะรัน SyncToursJob แบบ synchronous (ไม่ผ่าน queue)\n";

try {
    // Clear any stuck syncs first
    SyncLog::where('wholesaler_id', $config->wholesaler_id)
        ->where('status', 'running')
        ->update(['status' => 'failed', 'completed_at' => now(), 'error_summary' => ['message' => 'Cleared by test script']]);
    
    // Release any locks
    \Illuminate\Support\Facades\Cache::lock("sync_lock:wholesaler:{$config->wholesaler_id}")->forceRelease();
    
    echo "   Creating SyncToursJob...\n";
    $job = new SyncToursJob(
        wholesalerId: $config->wholesaler_id,
        transformedData: null,  // auto fetch from API
        syncType: 'full',
        limit: 2  // limit 2 tours for testing
    );
    
    echo "   Running job...\n";
    $job->handle();
    
    echo "✅ Job completed!\n";
    
    // ตรวจสอบ SyncLog
    $lastLog = SyncLog::where('wholesaler_id', $config->wholesaler_id)
        ->orderBy('id', 'desc')
        ->first();
    
    if ($lastLog) {
        echo "\n   SyncLog Result:\n";
        echo "   - Status: {$lastLog->status}\n";
        echo "   - Tours received: {$lastLog->tours_received}\n";
        echo "   - Tours created: {$lastLog->tours_created}\n";
        echo "   - Tours updated: {$lastLog->tours_updated}\n";
        echo "   - Tours skipped: {$lastLog->tours_skipped}\n";
        echo "   - Tours failed: {$lastLog->tours_failed}\n";
        echo "   - Periods received: {$lastLog->periods_received}\n";
        echo "   - Periods created: {$lastLog->periods_created}\n";
        echo "   - Periods updated: {$lastLog->periods_updated}\n";
        echo "   - Error count: {$lastLog->error_count}\n";
        echo "   - Duration: {$lastLog->duration_seconds}s\n";
        
        if ($lastLog->error_summary) {
            echo "   - Error summary: " . json_encode($lastLog->error_summary, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    // ตรวจสอบ Error Logs
    $errors = \App\Models\SyncErrorLog::where('sync_log_id', $lastLog->id ?? 0)->get();
    if ($errors->isNotEmpty()) {
        echo "\n   ❌ Sync Errors:\n";
        foreach ($errors as $err) {
            echo "   - [{$err->error_type}] {$err->entity_code}: {$err->error_message}\n";
        }
    }
    
    // ตรวจสอบ Tours ที่สร้าง/อัพเดท
    $recentTours = Tour::where('wholesaler_id', $config->wholesaler_id)
        ->orderBy('updated_at', 'desc')
        ->take(3)
        ->get();
    
    echo "\n   Recent Tours:\n";
    foreach ($recentTours as $tour) {
        $periodCount = Period::where('tour_id', $tour->id)->count();
        echo "   - [{$tour->tour_code}] {$tour->title} | Periods: {$periodCount} | Status: {$tour->status}\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR running SyncToursJob: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
    echo "   Trace:\n";
    // แสดง trace แค่ 15 บรรทัดแรก
    $traceLines = explode("\n", $e->getTraceAsString());
    foreach (array_slice($traceLines, 0, 15) as $line) {
        echo "   {$line}\n";
    }
}

echo "\n=== Done ===\n";
