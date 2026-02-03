<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get raw tour from API
$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(3);
$result = $adapter->fetchTours(null);
$rawTour = $result->tours[0];

echo "=== Raw Tour Data ===\n";
echo "code: " . ($rawTour['code'] ?? 'N/A') . "\n";
echo "vehicle: " . ($rawTour['vehicle'] ?? 'N/A') . "\n";
echo "countries: " . json_encode($rawTour['countries'] ?? []) . "\n";
echo "plans: " . (isset($rawTour['plans']) ? count($rawTour['plans']) . ' items' : 'N/A') . "\n";

// Test extractValue function
$extractValue = function($data, $path) use (&$extractValue) {
    if (empty($path)) return null;
    
    // Handle fallback paths with | separator
    if (strpos($path, '|') !== false) {
        $paths = explode('|', $path);
        foreach ($paths as $singlePath) {
            $value = $extractValue($data, trim($singlePath));
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }
    
    // Handle array notation like "countries[].code"
    if (strpos($path, '[]') !== false) {
        $parts = explode('[].', $path);
        $arrayKey = $parts[0];
        $fieldPath = $parts[1] ?? null;
        
        if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) return null;
        if (empty($data[$arrayKey])) return null;
        
        $firstItem = $data[$arrayKey][0] ?? null;
        if (!$firstItem) return null;
        
        if ($fieldPath) {
            return $extractValue($firstItem, $fieldPath);
        }
        return $firstItem;
    }
    
    // Normal dot notation
    $keys = explode('.', $path);
    $value = $data;
    foreach ($keys as $key) {
        if (!is_array($value) || !isset($value[$key])) return null;
        $value = $value[$key];
    }
    return $value;
};

echo "\n=== Testing extractValue ===\n";

// Test country extraction
$countryPath = 'countries[].code|countries[].name';
$countryValue = $extractValue($rawTour, $countryPath);
echo "Path: $countryPath\n";
echo "Value: " . var_export($countryValue, true) . "\n";

// Test transport extraction
$transportPath = 'vehicle';
$transportValue = $extractValue($rawTour, $transportPath);
echo "\nPath: $transportPath\n";
echo "Value: " . var_export($transportValue, true) . "\n";

// Test country lookup
echo "\n=== Testing Country Lookup ===\n";
if ($countryValue) {
    $country = \App\Models\Country::where('iso2', strtoupper($countryValue))
        ->orWhere('iso3', strtoupper($countryValue))
        ->orWhere('name_en', 'LIKE', '%' . $countryValue . '%')
        ->orWhere('name_th', 'LIKE', '%' . $countryValue . '%')
        ->first();
    
    if ($country) {
        echo "Found country: ID={$country->id}, Name={$country->name_th}\n";
    } else {
        echo "Country not found for: $countryValue\n";
    }
}

// Test transport lookup
echo "\n=== Testing Transport Lookup ===\n";
if ($transportValue) {
    // Try extract code from parentheses
    if (preg_match('/\(([A-Z0-9]{2,3})\)/', $transportValue, $matches)) {
        $code = $matches[1];
        $transport = \App\Models\Transport::where('code', $code)
            ->orWhere('code1', $code)
            ->first();
        
        if ($transport) {
            echo "Found by code '$code': ID={$transport->id}, Name={$transport->name}\n";
        }
    }
    
    if (!isset($transport) || !$transport) {
        // Try clean name
        $cleanName = preg_replace('/\s*\([^)]+\)\s*/', '', $transportValue);
        $transport = \App\Models\Transport::where('name', 'LIKE', '%' . $cleanName . '%')->first();
        
        if ($transport) {
            echo "Found by name '$cleanName': ID={$transport->id}, Name={$transport->name}\n";
        } else {
            echo "Transport not found for: $transportValue\n";
        }
    }
}
