<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Cities Table Summary ===\n\n";

// Total count
$total = DB::table('cities')->count();
echo "Total cities: $total\n\n";

// Group by country
echo "=== Cities by Country (Top 20) ===\n";
$byCountry = DB::table('cities')
    ->select('country_id', DB::raw('count(*) as count'))
    ->groupBy('country_id')
    ->orderByDesc('count')
    ->limit(20)
    ->get();

foreach ($byCountry as $row) {
    $country = DB::table('countries')->where('id', $row->country_id)->first();
    $countryName = $country ? ($country->name_en ?? $country->name ?? 'Unknown') : 'Unknown';
    echo "- Country #$row->country_id ($countryName): $row->count cities\n";
}

// Show table structure
echo "\n=== Cities Table Structure ===\n";
$columns = DB::select('DESCRIBE cities');
foreach ($columns as $col) {
    echo "- $col->Field ($col->Type)\n";
}

// Sample cities
echo "\n=== Sample Cities (first 20) ===\n";
$samples = DB::table('cities')->limit(20)->get();
foreach ($samples as $city) {
    $name = $city->name_en ?? $city->name ?? $city->name_th ?? 'N/A';
    $countryId = $city->country_id ?? 'N/A';
    echo "ID: $city->id | $name | country_id: $countryId\n";
}

// Check specific countries
echo "\n=== Cities in Key Countries ===\n";
$keyCountries = ['Thailand', 'China', 'Japan', 'Taiwan', 'Korea'];
foreach ($keyCountries as $countryName) {
    $country = DB::table('countries')
        ->where('name_en', 'LIKE', "%$countryName%")
        ->first();
    
    if ($country) {
        $cityCount = DB::table('cities')->where('country_id', $country->id)->count();
        echo "$countryName (ID: $country->id): $cityCount cities\n";
        
        // Show first 5 cities
        $cities = DB::table('cities')->where('country_id', $country->id)->limit(5)->get();
        foreach ($cities as $city) {
            $name = $city->name_en ?? $city->name ?? 'N/A';
            echo "  - $name\n";
        }
    } else {
        echo "$countryName: Country not found\n";
    }
}
