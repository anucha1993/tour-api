<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$logFile = storage_path('logs/laravel.log');
$lines = file($logFile);

echo "=== ALL entries from 09:09 to 09:14 (any type) ===\n";
$count = 0;
foreach ($lines as $line) {
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        $time = $m[1];
        if ($time >= '2026-03-28 09:09:17' && $time <= '2026-03-28 09:14:00') {
            $count++;
            echo substr($line, 0, 500) . "\n";
            if ($count >= 50) {
                echo "... (truncated, many more entries)\n";
                break;
            }
        }
    }
}
echo "\nTotal: {$count}\n";

// Also find the very last log entry for this date
echo "\n=== Last 5 log entries today ===\n";
$todayEntries = [];
foreach ($lines as $line) {
    if (preg_match('/^\[2026-03-28/', $line)) {
        $todayEntries[] = $line;
    }
}
foreach (array_slice($todayEntries, -5) as $entry) {
    echo substr($entry, 0, 500) . "\n";
}
