<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = \App\Models\WholesalerApiConfig::find(21);
if (!$c) { echo "Not found\n"; exit; }

echo "=== Integration 21 ===\n";
echo "wholesaler_id: {$c->wholesaler_id}\n";
echo "integration_type: {$c->integration_type}\n";
echo "headcode_file: " . ($c->headcode_file ?? 'NULL') . "\n";
echo "api_base_url: " . ($c->api_base_url ?? 'NULL') . "\n";
echo "sync_enabled: " . ($c->sync_enabled ? 'YES' : 'NO') . "\n";
echo "sync_schedule: " . ($c->sync_schedule ?? 'NULL') . "\n";
echo "sync_limit: " . ($c->sync_limit ?? 'NULL') . "\n";
echo "is_active: " . ($c->is_active ? 'YES' : 'NO') . "\n";

$w = $c->wholesaler;
echo "wholesaler: " . ($w ? $w->name . " (code={$w->code})" : 'NULL') . "\n";

// Check recent sync logs
$logs = \App\Models\SyncLog::where('wholesaler_id', $c->wholesaler_id)
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "\n=== Recent Sync Logs ===\n";
foreach ($logs as $log) {
    echo "[{$log->id}] {$log->created_at} | status={$log->status} | type={$log->sync_type}\n";
    echo "  tours_received={$log->tours_received} created={$log->tours_created} updated={$log->tours_updated} skipped={$log->tours_skipped}\n";
    if ($log->error_message) {
        echo "  error: " . mb_substr($log->error_message, 0, 200) . "\n";
    }
    if ($log->details) {
        $details = is_string($log->details) ? json_decode($log->details, true) : $log->details;
        if ($details) {
            echo "  details: " . json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
    }
    echo "---\n";
}

// Check tours count for this wholesaler
$tourCount = \App\Models\Tour::where('wholesaler_id', $c->wholesaler_id)->count();
echo "\nTours in DB for wholesaler {$c->wholesaler_id}: {$tourCount}\n";

// Check auth credentials (redacted)
$creds = $c->auth_credentials ?? [];
echo "\nAuth credentials keys: " . implode(', ', array_keys($creds)) . "\n";
if (isset($creds['endpoints'])) {
    echo "Endpoints: " . json_encode($creds['endpoints'], JSON_UNESCAPED_SLASHES) . "\n";
}
