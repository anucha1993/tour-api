<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check mapping paths for departure section across all wholesalers
$wholesalers = \App\Models\Wholesaler::all();

foreach ($wholesalers as $w) {
    $mapping = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $w->id)
        ->where('section_name', 'departure')
        ->first(['their_field_path']);
    
    if ($mapping) {
        // Extract array name from path like "periods[].start" -> "periods"
        preg_match('/^(\w+)\[\]/', $mapping->their_field_path, $matches);
        $arrayName = $matches[1] ?? 'N/A';
        
        echo "Wholesaler #{$w->id} ({$w->name}): periods array key = '{$arrayName}'\n";
        echo "  Example path: {$mapping->their_field_path}\n\n";
    }
}
