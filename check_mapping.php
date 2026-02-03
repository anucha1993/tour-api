<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\Wholesaler;

$wholesalerId = $argv[1] ?? 3;

echo "=== Integration for Wholesaler #{$wholesalerId} ===\n\n";

$config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();

if (!$config) {
    echo "Config not found!\n";
    
    // Check if wholesaler exists
    $wholesaler = Wholesaler::find($wholesalerId);
    if ($wholesaler) {
        echo "Wholesaler exists: {$wholesaler->name}\n";
        echo "But no WholesalerApiConfig found.\n";
    } else {
        echo "Wholesaler #{$wholesalerId} does not exist.\n";
    }
    exit;
}

$wholesalerName = $config->wholesaler?->name ?? "Unknown";

echo "Integration ID: {$config->id}\n";
echo "Wholesaler: {$wholesalerName}\n";
echo "API URL: {$config->api_base_url}\n";
echo "Auth Type: {$config->auth_type}\n";
echo "Sync Enabled: " . ($config->sync_enabled ? 'Yes' : 'No') . "\n";
echo "Sync Schedule: " . ($config->sync_schedule ?? 'Not set') . "\n";

// Check mappings
$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)->get();
echo "\n=== Field Mappings ({$mappings->count()}) ===\n";

if ($mappings->isEmpty()) {
    echo "No mappings found!\n";
} else {
    $grouped = $mappings->groupBy('section_name');
    foreach ($grouped as $section => $sectionMappings) {
        echo "\n[{$section}] - {$sectionMappings->count()} fields\n";
        foreach ($sectionMappings as $m) {
            $status = $m->is_active ? '✓' : '✗';
            echo "  {$status} {$m->our_field} ← {$m->their_field_path}\n";
        }
    }
}
