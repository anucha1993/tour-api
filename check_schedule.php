<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;

echo "Current time: " . date('Y-m-d H:i:s') . "\n";
echo "=== All Integration Sync Schedules ===\n\n";

$configs = WholesalerApiConfig::all();

foreach ($configs as $config) {
    $wholesalerName = $config->wholesaler?->name ?? "Wholesaler #{$config->wholesaler_id}";
    
    echo "Integration #{$config->id} - {$wholesalerName}\n";
    echo "  sync_enabled: " . ($config->sync_enabled ? 'true' : 'false') . "\n";
    echo "  sync_schedule: " . ($config->sync_schedule ?? 'NULL') . "\n";
    echo "  full_sync_schedule: " . ($config->full_sync_schedule ?? 'NULL') . "\n";
    
    if ($config->sync_schedule) {
        try {
            $cron = new \Cron\CronExpression($config->sync_schedule);
            echo "  Next run: " . $cron->getNextRunDate()->format('Y-m-d H:i:s') . "\n";
            echo "  Is due now: " . ($cron->isDue() ? 'YES ✓' : 'NO') . "\n";
        } catch (\Exception $e) {
            echo "  Invalid cron: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

echo "=== Testing: Will schedule dispatch jobs now? ===\n";
$configs = WholesalerApiConfig::where('sync_enabled', true)
    ->whereNotNull('sync_schedule')
    ->get();

foreach ($configs as $config) {
    $cron = new \Cron\CronExpression($config->sync_schedule);
    $isDue = $cron->isDue();
    echo "  Wholesaler #{$config->wholesaler_id}: {$config->sync_schedule} - " . ($isDue ? "WILL DISPATCH ✓" : "Not due") . "\n";
}
