<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check transport lookup
echo "=== Transport lookup for airline codes ===\n";
$codes = ['EK', 'QR', 'TG', 'WE'];
foreach ($codes as $code) {
    $transport = App\Models\Transport::where('code', $code)->first();
    if ($transport) {
        echo "  {$code} → id={$transport->id}, name={$transport->name}\n";
    } else {
        // Try partial match
        $transport = App\Models\Transport::where('code', 'LIKE', "%{$code}%")
            ->orWhere('name', 'LIKE', "%{$code}%")
            ->first();
        if ($transport) {
            echo "  {$code} → PARTIAL: id={$transport->id}, name={$transport->name}, code={$transport->code}\n";
        } else {
            echo "  {$code} → NOT FOUND\n";
        }
    }
}

// Check the mapping's transform config
echo "\n=== Transport mapping config ===\n";
$mapping = App\Models\WholesalerFieldMapping::where('wholesaler_id', 57)
    ->where('our_field', 'transport_id')
    ->first();
if ($mapping) {
    echo "  transform_type: {$mapping->transform_type}\n";
    echo "  transform_config: " . json_encode($mapping->transform_config, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  their_field_path: {$mapping->their_field_path}\n";
}

// Check country lookup
echo "\n=== Country lookup for country_name_eng ===\n";
$names = ['EUROPE', 'JAPAN', 'KOREA', 'CHINA'];
foreach ($names as $name) {
    $country = DB::table('countries')->where('name_en', $name)->first();
    if ($country) {
        echo "  {$name} → id={$country->id}, name_en={$country->name_en}\n";
    } else {
        $country = DB::table('countries')->where('name_en', 'LIKE', "%{$name}%")->first();
        if ($country) {
            echo "  {$name} → PARTIAL: id={$country->id}, name_en={$country->name_en}\n";
        } else {
            echo "  {$name} → NOT FOUND\n";
        }
    }
}

// Check country mapping config
echo "\n=== Country mapping config ===\n";
$mapping = App\Models\WholesalerFieldMapping::where('wholesaler_id', 57)
    ->where('our_field', 'primary_country_id')
    ->first();
if ($mapping) {
    echo "  transform_type: {$mapping->transform_type}\n";
    echo "  transform_config: " . json_encode($mapping->transform_config, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  their_field_path: {$mapping->their_field_path}\n";
}

// Check all distinct country_name_eng values from API
echo "\n=== All country codes from API ===\n";
$url = 'https://tour-api.bestinternational.com/api/tour-programs/v2/tour/all?page=1&limit=999';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
]);
$raw = curl_exec($ch);
curl_close($ch);
$data = json_decode($raw, true);
$tours = $data['data']['data'] ?? [];
$countries = [];
foreach ($tours as $t) {
    $key = ($t['country_name_eng'] ?? '') . ' (' . ($t['country_code'] ?? '') . ')';
    $countries[$key] = ($countries[$key] ?? 0) + 1;
}
arsort($countries);
foreach ($countries as $k => $v) {
    echo "  {$k}: {$v} tours\n";
}

echo "\n=== All airlines from API ===\n";
$airlines = [];
foreach ($tours as $t) {
    $key = ($t['airline_code'] ?? '') . ' - ' . ($t['airline_name'] ?? '');
    $airlines[$key] = ($airlines[$key] ?? 0) + 1;
}
arsort($airlines);
foreach ($airlines as $k => $v) {
    echo "  {$k}: {$v} tours\n";
}
