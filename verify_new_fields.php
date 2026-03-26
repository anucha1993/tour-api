<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tours = \App\Models\Tour::where('wholesaler_id', 40)
    ->whereNotNull('primary_country_id')
    ->orWhere(function($q) {
        $q->where('wholesaler_id', 40)->whereNotNull('description');
    })
    ->limit(5)
    ->get(['id', 'tour_code', 'title', 'primary_country_id', 'description', 'min_price', 'price_adult', 'external_id']);

echo "=== TTN Japan Tours — Verify new fields ===\n\n";
foreach ($tours as $t) {
    echo "[{$t->id}] {$t->tour_code} (ext_id={$t->external_id})\n";
    echo "  title: " . mb_substr($t->title ?? '', 0, 60) . "\n";
    echo "  primary_country_id: " . ($t->primary_country_id ?? 'NULL') . "\n";
    echo "  description: " . ($t->description ? mb_substr($t->description, 0, 80) . '...' : 'NULL') . "\n";
    echo "  min_price: " . number_format($t->min_price ?? 0) . "\n";
    echo "  price_adult: " . number_format($t->price_adult ?? 0) . "\n";
    
    // Check country pivot
    $countries = $t->countries()->pluck('countries.name_en', 'countries.id')->toArray();
    echo "  countries: " . ($countries ? json_encode($countries) : 'NONE') . "\n";
    
    $periods = \App\Models\Period::where('tour_id', $t->id)->count();
    echo "  periods: {$periods}\n\n";
}
