<?php
/**
 * Check period mappings for wholesaler 6
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerFieldMapping;

echo "=== Departure Mappings for Wholesaler 6 ===\n\n";

$mappings = WholesalerFieldMapping::where('wholesaler_id', 6)
    ->where('section_name', 'departure')
    ->where('is_active', true)
    ->get();

foreach ($mappings as $m) {
    $path = $m->their_field_path ?? $m->their_field;
    echo "{$m->our_field} => {$path}\n";
}

echo "\nTotal: " . $mappings->count() . " mappings\n";
