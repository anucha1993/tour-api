<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NotificationService;

echo "=== Testing NotificationService ===\n\n";

$service = app(NotificationService::class);

echo "1. isEnabled(): " . ($service->isEnabled() ? 'true' : 'false') . "\n";
echo "2. isConfigured(): " . ($service->isConfigured() ? 'true' : 'false') . "\n";

echo "\n3. Testing notifyIntegration(2, 'sync_error')...\n";
$result = $service->notifyIntegration(2, 'sync_error', [
    'error' => 'Test error message from check script',
    'sync_type' => 'test',
]);
echo "Result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

echo "\n4. Checking Laravel log for details...\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -30);
    foreach ($lastLines as $line) {
        if (stripos($line, 'NotificationService') !== false || stripos($line, 'Email') !== false) {
            echo $line;
        }
    }
}
