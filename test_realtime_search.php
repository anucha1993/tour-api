<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$wholesalerId = $argv[1] ?? 3;

$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();

if (!$config) {
    echo "Config not found!\n";
    exit;
}

// Check mapping for country and airline
echo "=== Mapping for Country/Airline ===\n";
$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('section_name', 'tour')
    ->get(['our_field', 'their_field_path']);

foreach ($mappings as $m) {
    if (str_contains($m->our_field, 'country') || str_contains($m->our_field, 'transport') || str_contains($m->our_field, 'airline')) {
        echo "  {$m->our_field} <- {$m->their_field_path}\n";
    }
}

$searchService = new \App\Services\UnifiedSearchService();

echo "\n=== Testing Realtime Search for Wholesaler #{$wholesalerId} ===\n";

try {
    $result = $searchService->searchWholesaler($config, []);
    
    echo "Total tours: " . count($result['tours']) . "\n\n";
    
    if (!empty($result['tours'])) {
        $tour = $result['tours'][0];
        echo "=== First Tour ===\n";
        echo "Title: " . ($tour['title'] ?? 'N/A') . "\n";
        echo "Tour Code: " . ($tour['wholesaler_tour_code'] ?? 'N/A') . "\n";
        echo "Country: " . ($tour['primary_country_id'] ?? $tour['country'] ?? 'N/A') . "\n";
        echo "Transport/Airline: " . ($tour['transport_id'] ?? $tour['airline'] ?? 'N/A') . "\n";
        echo "Periods count: " . count($tour['periods'] ?? []) . "\n";
        
        // Show raw keys for debugging
        echo "\n=== Available Keys in Tour ===\n";
        echo implode(', ', array_keys($tour)) . "\n";
        
        if (!empty($tour['periods'])) {
            echo "\n=== First Period ===\n";
            $period = $tour['periods'][0];
            echo "Departure: " . ($period['departure_date'] ?? $period['start_date'] ?? 'N/A') . "\n";
            echo "Price: " . ($period['price_adult'] ?? 'N/A') . "\n";
            echo "Available: " . ($period['available'] ?? $period['available_seats'] ?? 'N/A') . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
