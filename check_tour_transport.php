<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tours = \App\Models\Tour::where('wholesaler_id', 3)->get();

echo "Tours from Wholesaler #3:\n";
foreach ($tours as $tour) {
    echo "  {$tour->tour_code} | transport_id=" . ($tour->transport_id ?: 'NULL') . " | country_id=" . ($tour->primary_country_id ?: 'NULL') . "\n";
}
