<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Read log file and find entries from 09:06-09:14
$logFile = storage_path('logs/laravel.log');
$lines = file($logFile);

$collecting = false;
$entries = [];
$currentEntry = '';

foreach ($lines as $line) {
    // Match log timestamp
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        if (!empty($currentEntry) && $collecting) {
            $entries[] = $currentEntry;
        }
        $time = $m[1];
        $collecting = ($time >= '2026-03-28 09:06:00' && $time <= '2026-03-28 09:15:00');
        $currentEntry = $collecting ? $line : '';
    } else if ($collecting) {
        $currentEntry .= $line;
    }
}
if (!empty($currentEntry) && $collecting) {
    $entries[] = $currentEntry;
}

echo "Found " . count($entries) . " log entries between 09:06-09:15\n\n";

// Show first 30 and last 20
$show = array_merge(
    array_slice($entries, 0, 30),
    ['... (' . max(0, count($entries) - 50) . ' entries omitted) ...'],
    array_slice($entries, -20)
);

foreach ($show as $entry) {
    // Truncate each entry to 200 chars
    echo substr($entry, 0, 300) . "\n";
}
