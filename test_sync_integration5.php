<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Jobs\SyncToursJob;
use Illuminate\Support\Facades\Log;

echo "=== Testing Sync for Integration 5 (Check-in Group) ===\n\n";

$config = WholesalerApiConfig::find(5);
echo "Config: {$config->api_base_url}\n";
echo "Sync Mode: {$config->sync_mode}\n\n";

try {
    echo "Running SyncToursJob...\n";
    $job = new SyncToursJob($config->id);
    $job->handle();
    echo "Done!\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
