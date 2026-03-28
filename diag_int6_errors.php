<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Search log file for errors around sync time (08:44:41 - 08:45:18)
$logFile = storage_path('logs/laravel.log');
if (!file_exists($logFile)) {
    echo "Log file not found\n";
    exit;
}

$fh = fopen($logFile, 'r');
$errors = [];
$currentEntry = '';
$inRelevantTime = false;

while (($line = fgets($fh)) !== false) {
    // Check if this is a new log entry
    if (preg_match('/^\[2026-03-28 (\d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        // Save previous entry if it was an error in our time range
        if ($inRelevantTime && str_contains($currentEntry, 'ERROR')) {
            $errors[] = substr($currentEntry, 0, 800);
        }
        
        $time = $m[1];
        // Focus on sync time range (08:44 - 08:46 UTC = 15:44-15:46 local)
        // Actually check wider range
        $inRelevantTime = ($time >= '08:44:00' && $time <= '08:46:00');
        $currentEntry = $line;
    } else {
        $currentEntry .= $line;
    }
}
fclose($fh);

// Don't forget last entry
if ($inRelevantTime && str_contains($currentEntry, 'ERROR')) {
    $errors[] = substr($currentEntry, 0, 800);
}

echo "=== Errors during sync (08:44-08:46 UTC) ===\n";
echo "Found " . count($errors) . " error entries\n\n";

// Show unique error types
$uniqueErrors = [];
foreach ($errors as $err) {
    // Extract key part of error
    if (preg_match('/ERROR: (.+?)(\{|$)/s', $err, $m)) {
        $key = trim($m[1]);
        if (!isset($uniqueErrors[$key])) {
            $uniqueErrors[$key] = ['count' => 0, 'sample' => $err];
        }
        $uniqueErrors[$key]['count']++;
    }
}

echo "=== Unique Error Types ===\n";
foreach ($uniqueErrors as $key => $info) {
    echo "\n[x{$info['count']}] {$key}\n";
    echo "Sample:\n" . $info['sample'] . "\n";
    echo "---\n";
}

// If no errors found in time range, show all errors from today
if (empty($errors)) {
    echo "\nNo errors in exact time range. Checking broader range...\n";
    
    $fh = fopen($logFile, 'r');
    $todayErrors = [];
    $currentEntry = '';
    $isToday = false;
    
    while (($line = fgets($fh)) !== false) {
        if (preg_match('/^\[2026-03-28/', $line)) {
            if ($isToday && str_contains($currentEntry, 'ERROR') && (str_contains($currentEntry, 'SyncTours') || str_contains($currentEntry, 'wholesaler_id'))) {
                $todayErrors[] = substr($currentEntry, 0, 800);
            }
            $isToday = true;
            $currentEntry = $line;
        } elseif ($isToday) {
            $currentEntry .= $line;
        }
    }
    fclose($fh);
    
    echo "Found " . count($todayErrors) . " sync-related errors today\n";
    foreach (array_slice($todayErrors, -5) as $err) {
        echo "\n" . $err . "\n---\n";
    }
}
