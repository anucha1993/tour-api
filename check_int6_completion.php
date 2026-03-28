<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Read log file and find the "Completed" entry
$logFile = storage_path('logs/laravel.log');
$lines = file($logFile);

echo "=== Looking for SyncToursJob completion/error entries (09:06-09:15) ===\n";
$inRange = false;
foreach ($lines as $line) {
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        $time = $m[1];
        $inRange = ($time >= '2026-03-28 09:06:00' && $time <= '2026-03-28 09:15:00');
    }
    if ($inRange) {
        if (str_contains($line, 'SyncToursJob: Completed') ||
            str_contains($line, 'SyncToursJob: Failed') ||
            str_contains($line, 'SyncToursJob: Starting processTours') ||
            str_contains($line, 'local.ERROR') ||
            str_contains($line, 'local.WARNING')) {
            echo substr($line, 0, 500) . "\n";
        }
    }
}
