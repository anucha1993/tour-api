// Test SyncToursJob inline for both fixed wholesalers
// wholesaler 40 (config 22) — ไทยเที่ยวนอกทัวร์
// wholesaler 50 (config 24) — Superb Holidayz

$targets = [
    ['wid' => 40, 'name' => 'ไทยเที่ยวนอกทัวร์ (config 22)'],
    ['wid' => 50, 'name' => 'Superb Holidayz (config 24)'],
];

foreach ($targets as $t) {
    echo "\n\n===== TESTING wholesaler {$t['wid']} — {$t['name']} =====\n";

    $config = \App\Models\WholesalerApiConfig::where('wholesaler_id', $t['wid'])->first();
    if (!$config) {
        echo "  ❌ config not found\n";
        continue;
    }
    echo "  auth_type       = {$config->auth_type}\n";
    echo "  auth_credentials= " . json_encode($config->auth_credentials, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  sync_enabled    = " . ($config->sync_enabled ? 'true' : 'false') . "\n";
    echo "  integration_type= " . ($config->integration_type ?? 'config') . "\n";

    try {
        $start = microtime(true);
        // Use small limit to keep test fast; skip period processing (only test tour-list fetch)
        $job = new \App\Jobs\SyncToursJob($t['wid'], null, 'manual', 3);
        // Do NOT process periods inline — we only want to prove the header bug is gone
        $job->handle();
        $ms = (int)((microtime(true) - $start) * 1000);
        echo "  ✅ SUCCESS in {$ms}ms\n";
    } catch (\Throwable $e) {
        echo "  ❌ FAILED: " . get_class($e) . "\n";
        echo "  message: " . $e->getMessage() . "\n";
        // Show only first 3 frames of trace
        $trace = explode("\n", $e->getTraceAsString());
        echo "  trace:\n";
        foreach (array_slice($trace, 0, 4) as $line) {
            echo "    {$line}\n";
        }
    }
}

echo "\n\n===== DONE =====\n";
