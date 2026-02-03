<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$wholesalerId = $argv[1] ?? 3;

// Check itinerary mappings
$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('section_name', 'itinerary')
    ->get(['our_field', 'their_field', 'their_field_path', 'is_active']);

echo "=== Itinerary Mappings for Wholesaler #{$wholesalerId} ===\n";
if ($mappings->isEmpty()) {
    echo "❌ No itinerary mappings found!\n";
} else {
    foreach ($mappings as $m) {
        $source = $m->their_field_path ?: $m->their_field;
        $status = $m->is_active ? '✓' : '✗';
        echo "  {$status} {$m->our_field} ← {$source}\n";
    }
}

// Check if tour has itineraries
$tour = \App\Models\Tour::where('wholesaler_id', $wholesalerId)->orderBy('id', 'desc')->first();
if ($tour) {
    echo "\n=== Latest Tour (ID: {$tour->id}) ===\n";
    echo "Title: {$tour->title}\n";
    
    $itineraries = \App\Models\TourItinerary::where('tour_id', $tour->id)->get();
    echo "Itineraries count: " . $itineraries->count() . "\n";
    
    if ($itineraries->isNotEmpty()) {
        echo "\nItinerary days:\n";
        foreach ($itineraries as $itin) {
            echo "  Day {$itin->day_number}: {$itin->title}\n";
        }
    }
}

// Check API config - sync_mode
$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();
if ($config) {
    echo "\n=== API Config ===\n";
    echo "Sync Mode: " . ($config->sync_mode ?? 'single_phase') . "\n";
    $creds = $config->auth_credentials ?? [];
    $endpoints = $creds['endpoints'] ?? [];
    echo "Itineraries Endpoint: " . ($endpoints['itineraries'] ?? 'Not configured') . "\n";
}
