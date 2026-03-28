<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$logFile = storage_path('logs/laravel.log');
$lines = file($logFile);

// Find ALL entries from 09:13 to 09:14
echo "=== All log entries from 09:12 to 09:15 ===\n";
$inRange = false;
$count = 0;
foreach ($lines as $line) {
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        $time = $m[1];
        $inRange = ($time >= '2026-03-28 09:12:00' && $time <= '2026-03-28 09:15:00');
    }
    if ($inRange) {
        $count++;
        // Show all entries (truncated)
        echo substr($line, 0, 400) . "\n";
    }
}
echo "\nTotal entries: {$count}\n";

// Also check for any "SyncToursJob" entries in 09:13-09:14
echo "\n=== SyncToursJob entries 09:06-09:30 ===\n";
$all = [];
foreach ($lines as $line) {
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        $time = $m[1];
        if ($time >= '2026-03-28 09:06:00' && $time <= '2026-03-28 09:30:00') {
            if (str_contains($line, 'SyncToursJob:') && !str_contains($line, 'tourSection') && !str_contains($line, 'tourFields') && !str_contains($line, 'Extracted cities') && !str_contains($line, 'Transform field') && !str_contains($line, 'After transform')) {
                $all[] = substr($line, 0, 400);
            }
        }
    }
}
foreach ($all as $entry) {
    echo $entry . "\n";
}
