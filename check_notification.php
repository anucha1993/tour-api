<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\Setting;
use App\Models\SyncLog;

echo "=== Last Sync Log ===\n";
$lastLog = SyncLog::where('wholesaler_id', 2)->orderBy('created_at', 'desc')->first();
if ($lastLog) {
    echo "ID: " . $lastLog->id . "\n";
    echo "Created: " . $lastLog->created_at . "\n";
    echo "Status: " . $lastLog->status . "\n";
    echo "Error: " . json_encode($lastLog->error_summary) . "\n";
}

echo "\n=== Integration 2 Notification Settings ===\n";
$config = WholesalerApiConfig::where('wholesaler_id', 2)->first();
if ($config) {
    echo "Config ID (primary key): " . $config->id . "\n";
    echo "wholesaler_id: " . $config->wholesaler_id . "\n";
    echo "notifications_enabled: " . ($config->notifications_enabled ? 'true' : 'false') . "\n";
    echo "notification_emails: " . json_encode($config->notification_emails) . "\n";
    echo "notification_types: " . json_encode($config->notification_types) . "\n";
} else {
    echo "Config not found!\n";
}

echo "\n=== SMTP Settings ===\n";
$smtp = Setting::get('smtp_config');
if ($smtp) {
    echo "SMTP host: " . ($smtp['host'] ?? 'NOT SET') . "\n";
    echo "SMTP port: " . ($smtp['port'] ?? 'NOT SET') . "\n";
    echo "SMTP enabled: " . (($smtp['enabled'] ?? false) ? 'true' : 'false') . "\n";
    echo "SMTP from_address: " . ($smtp['from_address'] ?? 'NOT SET') . "\n";
    echo "Has password: " . (!empty($smtp['password']) ? 'yes' : 'no') . "\n";
} else {
    echo "SMTP not configured!\n";
}

echo "\n=== Recent Sync Logs (Last 5) ===\n";
$logs = \App\Models\SyncLog::where('wholesaler_id', 2)
    ->orderBy('created_at', 'desc')
    ->take(5)
    ->get(['id', 'status', 'error_summary', 'created_at']);

foreach ($logs as $log) {
    echo "- [{$log->created_at}] Status: {$log->status}";
    if ($log->error_summary) {
        echo " | Error: " . json_encode($log->error_summary);
    }
    echo "\n";
}
