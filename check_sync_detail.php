<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$log = DB::table('sync_logs')->where('id', 457)->first();
if ($log) {
    $cols = (array) $log;
    foreach ($cols as $k => $v) {
        echo str_pad($k, 30) . ": " . ($v ?? 'NULL') . PHP_EOL;
    }
}

echo PHP_EOL . "=== Laravel Log (last 50 lines) ===" . PHP_EOL;
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last50 = array_slice($lines, -50);
    foreach ($last50 as $line) {
        echo $line;
    }
} else {
    echo "No log file found" . PHP_EOL;
}
