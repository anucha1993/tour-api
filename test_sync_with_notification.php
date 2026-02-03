<?php

/**
 * ทดสอบ SyncToursJob โดยตรง (ไม่ผ่าน Queue)
 * เพื่อทดสอบว่า notification ทำงานถูกต้อง
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\SyncToursJob;
use Illuminate\Support\Facades\Log;

echo "=== Testing SyncToursJob directly (not via queue) ===\n\n";

$wholesalerId = 2;

try {
    echo "Running SyncToursJob for wholesaler ID: {$wholesalerId}\n";
    echo "This will trigger notification on failure...\n\n";
    
    // Run job directly (synchronously)
    $job = new SyncToursJob($wholesalerId, null, 'incremental');
    $job->handle();
    
    echo "\n✅ Sync completed successfully!\n";
} catch (\Exception $e) {
    echo "\n❌ Sync failed: " . $e->getMessage() . "\n";
    echo "\nCheck your email for notification!\n";
}

echo "\n=== Checking latest logs for NotificationService ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    foreach ($lastLines as $line) {
        if (stripos($line, 'NotificationService') !== false) {
            echo $line;
        }
    }
}
