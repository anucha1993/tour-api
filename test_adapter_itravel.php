<?php
/**
 * ทดสอบ HeadcodeItravelAdapter โดยตรง (ไม่ผ่าน SyncToursJob)
 * Run: php test_adapter_itravel.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WholesalerAdapters\AdapterFactory;
use App\Models\WholesalerApiConfig;

$config = WholesalerApiConfig::where('integration_type', 'headcode')
    ->where('headcode_file', 'itravel')
    ->first();

if (!$config) { echo "ERROR: config not found\n"; exit(1); }

echo "Config id={$config->id}, wholesaler_id={$config->wholesaler_id}\n\n";

echo "Creating adapter via AdapterFactory...\n";
$adapter = AdapterFactory::create($config->wholesaler_id);
echo "Adapter class: " . get_class($adapter) . "\n\n";

echo "Calling fetchTours('__sample__') — 1 tour with periods...\n";
$start = microtime(true);
$result = $adapter->fetchTours('__sample__');
$elapsed = round((microtime(true) - $start) * 1000);

echo "  success : " . ($result->success ? 'true' : 'false') . "\n";
echo "  elapsed : {$elapsed}ms\n";
echo "  tours   : " . count($result->tours) . "\n\n";

if (!$result->success || empty($result->tours)) {
    echo "ERROR: No tours returned\n";
    exit(1);
}

$tour = $result->tours[0];
echo "=== Tour structure (first tour) ===\n";

// Check for pre-mapped structure (has 'tour' key)
if (isset($tour['tour'])) {
    echo "✅ Pre-mapped format detected (has 'tour' key)\n\n";

    echo "--- 'tour' section ---\n";
    foreach ($tour['tour'] as $k => $v) {
        echo "  {$k}: " . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\n--- 'departure' section (" . count($tour['departure']) . " periods) ---\n";
    foreach (array_slice($tour['departure'], 0, 2) as $i => $dep) {
        echo "  Period [{$i}]:\n";
        foreach ($dep as $k => $v) {
            echo "    {$k}: " . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    echo "❌ RAW format (missing 'tour' key) — SyncToursJob will try to transform but has no mappings!\n";
    echo "Keys found: " . implode(', ', array_keys($tour)) . "\n";
}
