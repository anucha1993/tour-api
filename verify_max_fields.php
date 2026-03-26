<?php
/**
 * Verify maximum field mapping — check all new fields added to TTN Japan adapter
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tours = \App\Models\Tour::where('wholesaler_id', 40)
    ->orderBy('updated_at', 'desc')
    ->limit(3)
    ->get();

echo "=== TTN Japan — Maximum Field Mapping Verification ===\n\n";

foreach ($tours as $t) {
    echo "[{$t->id}] {$t->wholesaler_tour_code}\n";
    echo "  title: " . mb_substr($t->title ?? '', 0, 70) . "\n";
    echo "  primary_country_id: " . ($t->primary_country_id ?? 'NULL') . "\n";
    echo "  description: " . ($t->description ? mb_substr($t->description, 0, 100) : 'NULL') . "\n";
    echo "  region: " . ($t->region ?? 'NULL') . "\n";
    echo "  sub_region: " . ($t->sub_region ?? 'NULL') . "\n";
    echo "  hotel_star: " . ($t->hotel_star ?? 'NULL') . "\n";
    echo "  highlights: " . (is_array($t->highlights) ? json_encode($t->highlights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($t->highlights ?? 'NULL')) . "\n";
    echo "  hashtags: " . (is_array($t->hashtags) ? json_encode($t->hashtags, JSON_UNESCAPED_UNICODE) : ($t->hashtags ?? 'NULL')) . "\n";
    echo "  departure_airports: " . (is_array($t->departure_airports) ? json_encode($t->departure_airports) : ($t->departure_airports ?? 'NULL')) . "\n";
    echo "  docx_url: " . ($t->docx_url ? 'YES (' . mb_substr($t->docx_url, 0, 60) . ')' : 'NULL') . "\n";
    echo "  meta_title: " . ($t->meta_title ? mb_substr($t->meta_title, 0, 70) : 'NULL') . "\n";
    echo "  meta_description: " . ($t->meta_description ? mb_substr($t->meta_description, 0, 100) : 'NULL') . "\n";
    echo "  cover_image_url: " . ($t->cover_image_url ? 'YES' : 'NULL') . "\n";
    echo "  pdf_url: " . ($t->pdf_url ? 'YES' : 'NULL') . "\n";
    echo "  min_price: " . ($t->min_price ? number_format($t->min_price) : 'NULL') . "\n";
    echo "  price_adult: " . ($t->price_adult ? number_format($t->price_adult) : 'NULL') . "\n";
    echo "  duration: " . ($t->duration_days ?? '?') . "D/" . ($t->duration_nights ?? '?') . "N\n";
    echo "  transport_id: " . ($t->transport_id ?? 'NULL') . "\n";
    echo "  total_departures: " . ($t->total_departures ?? 'NULL') . "\n";
    echo "  available_seats: " . ($t->available_seats ?? 'NULL') . "\n";
    echo "  next_departure_date: " . ($t->next_departure_date ?? 'NULL') . "\n";

    // Check itineraries
    $itinCount = $t->itineraries()->count();
    $firstItin = $t->itineraries()->orderBy('day_number')->first();
    echo "  itineraries: {$itinCount}";
    if ($firstItin) {
        echo " | day1: day_number=" . ($firstItin->day_number ?? '?')
           . ", places=" . ($firstItin->places ? 'YES' : 'NO')
           . ", sort_order=" . ($firstItin->sort_order ?? '?')
           . ", data_source=" . ($firstItin->data_source ?? '?');
    }
    echo "\n";

    // Check periods for booked & period_code
    $periods = $t->periods()->limit(2)->get();
    foreach ($periods as $p) {
        echo "  period: ext={$p->external_id} code=" . ($p->period_code ?? 'NULL')
           . " booked=" . ($p->booked ?? 'NULL')
           . " avail=" . ($p->available ?? 'NULL')
           . " status={$p->status}\n";
    }
    echo "---\n";
}

// Summary
$total = \App\Models\Tour::where('wholesaler_id', 40)->count();
$withDesc = \App\Models\Tour::where('wholesaler_id', 40)->whereNotNull('description')->where('description', '!=', '')->count();
$withRegion = \App\Models\Tour::where('wholesaler_id', 40)->whereNotNull('region')->count();
$withHashtags = \App\Models\Tour::where('wholesaler_id', 40)->whereNotNull('hashtags')->count();
$withDocx = \App\Models\Tour::where('wholesaler_id', 40)->whereNotNull('docx_url')->where('docx_url', '!=', '')->count();
$withMeta = \App\Models\Tour::where('wholesaler_id', 40)->whereNotNull('meta_title')->count();
$withCountry = \App\Models\Tour::where('wholesaler_id', 40)->whereNotNull('primary_country_id')->count();
$withAirports = \App\Models\Tour::where('wholesaler_id', 40)->whereNotNull('departure_airports')->count();

echo "\n=== Summary (of {$total} total tours) ===\n";
echo "  with description:       {$withDesc}\n";
echo "  with region:            {$withRegion}\n";
echo "  with hashtags:          {$withHashtags}\n";
echo "  with docx_url:          {$withDocx}\n";
echo "  with meta_title:        {$withMeta}\n";
echo "  with primary_country_id: {$withCountry}\n";
echo "  with departure_airports: {$withAirports}\n";
