<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$wholesalerId = $argv[1] ?? 3;

$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('section_name', 'departure')
    ->get(['our_field', 'their_field', 'their_field_path']);

echo "=== Departure Section Mappings for Wholesaler #{$wholesalerId} ===\n";
foreach ($mappings as $m) {
    $source = $m->their_field_path ?: $m->their_field;
    echo "  {$m->our_field} ← {$source}\n";
}

// Check Period model fillable
echo "\n=== Period Model Fillable Fields ===\n";
$period = new \App\Models\Period();
print_r($period->getFillable());
