<?php
/**
 * Check offers for periods of tour 272
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Period;

$periods = Period::where('tour_id', 272)->take(5)->pluck('id');
echo "Period IDs: " . $periods->implode(', ') . "\n\n";

$offers = DB::table('offers')->whereIn('period_id', $periods)->take(5)->get();
echo "Offers found: " . count($offers) . "\n";

foreach ($offers as $o) {
    echo "  Period: {$o->period_id}, Price Adult: {$o->price_adult}, Price Single: " . ($o->price_single ?? 'N/A') . "\n";
}
