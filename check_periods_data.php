<?php
/**
 * Check periods data in database
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Period;

$periods = Period::where('tour_id', 272)->orderBy('id', 'desc')->take(3)->get();
echo "Periods found: " . count($periods) . "\n";

foreach ($periods as $p) {
    echo "\n=== Period ID: {$p->id} ===\n";
    $attrs = $p->getAttributes();
    foreach ($attrs as $key => $value) {
        echo "{$key}: {$value}\n";
    }
}
