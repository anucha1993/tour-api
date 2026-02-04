<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use GuzzleHttp\Client;

$config = WholesalerApiConfig::find(11);
$credentials = $config->auth_credentials;
$endpoints = $credentials['endpoints'] ?? [];

echo "=== Checking API Structure for Integration 11 ===\n\n";

// Fetch detail for tour 14346
$url = str_replace('{external_id}', '14346', $endpoints['periods']);
echo "Fetching: $url\n\n";

$client = new Client();
$resp = $client->get($url, ['headers' => $credentials['headers'] ?? []]);
$body = json_decode($resp->getBody(), true);

// Check response structure
echo "Response keys: " . json_encode(array_keys($body)) . "\n\n";

// Handle different response formats
if (isset($body['data']) && is_array($body['data'])) {
    $data = $body['data'];
    // If data is indexed array, take first item
    if (isset($data[0]) && is_array($data[0])) {
        $data = $data[0];
    }
} else {
    $data = $body;
}

echo "=== Top Level Keys ===\n";
print_r(array_keys($data));

// Check itinerary
if (isset($data['tour_itinerary'])) {
    echo "\n=== tour_itinerary ===\n";
    echo "Count: " . count($data['tour_itinerary']) . "\n";
    if (!empty($data['tour_itinerary'])) {
        echo "Sample (first item):\n";
        print_r($data['tour_itinerary'][0]);
    }
}

// Check tour_flight
if (isset($data['tour_flight'])) {
    echo "\n=== tour_flight ===\n";
    echo "Count: " . count($data['tour_flight']) . "\n";
    if (!empty($data['tour_flight'])) {
        echo "Sample (first item):\n";
        print_r($data['tour_flight'][0]);
    }
}

// Check tour_city
if (isset($data['tour_city'])) {
    echo "\n=== tour_city ===\n";
    echo "Count: " . count($data['tour_city']) . "\n";
    if (!empty($data['tour_city'])) {
        echo "Sample (first item):\n";
        print_r($data['tour_city'][0]);
    }
}

// Check tour_country
if (isset($data['tour_country'])) {
    echo "\n=== tour_country ===\n";
    echo "Count: " . count($data['tour_country']) . "\n";
    if (!empty($data['tour_country'])) {
        print_r($data['tour_country']);
    }
}

// Check region
if (isset($data['tour_region'])) {
    echo "\n=== tour_region ===\n";
    print_r($data['tour_region']);
}

// Check tour_daily (itinerary)
if (isset($data['tour_daily'])) {
    echo "\n=== tour_daily (itinerary) ===\n";
    echo "Count: " . count($data['tour_daily']) . "\n";
    if (!empty($data['tour_daily'])) {
        echo "Sample (first item):\n";
        print_r($data['tour_daily'][0]);
    }
}

// Check tour_area
if (isset($data['tour_area'])) {
    echo "\n=== tour_area ===\n";
    print_r($data['tour_area']);
}

echo "\n=== Done ===\n";
