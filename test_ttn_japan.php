<?php
/**
 * Quick test: TTN Japan headcode adapter (integration 22)
 * Usage: php test_ttn_japan.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = \App\Models\WholesalerApiConfig::find(22);
if (!$config) {
    echo "❌ Integration 22 not found\n";
    exit(1);
}

echo "=== Integration 22 ===\n";
echo "Wholesaler ID: {$config->wholesaler_id}\n";
echo "Type: {$config->integration_type}\n";
echo "Headcode: {$config->headcode_file}\n";
echo "Base URL: {$config->api_base_url}\n";
echo "Sync: " . ($config->sync_enabled ? 'ON' : 'OFF') . "\n\n";

// Load the adapter
$adapterPath = storage_path('headcode/' . $config->headcode_file . '.php');
if (!file_exists($adapterPath)) {
    echo "❌ Adapter not found: {$adapterPath}\n";
    exit(1);
}
echo "✅ Adapter file: {$adapterPath}\n\n";

require_once $adapterPath;

// Find the class name
$className = null;
foreach (get_declared_classes() as $cls) {
    if (str_starts_with($cls, 'Headcode') && str_contains($cls, 'TtnJapan')) {
        $className = $cls;
        break;
    }
}
if (!$className) {
    echo "❌ Adapter class not found\n";
    exit(1);
}
echo "✅ Class: {$className}\n\n";

// Instantiate
$adapter = new $className($config, $config->wholesaler_id);

// Test with all tours (no limit)
echo "=== Fetching ALL tours ===\n";
try {
    $result = $adapter->fetchTours();
    
    echo "Success: " . ($result->success ? 'YES' : 'NO') . "\n";
    echo "Tours: " . count($result->tours) . "\n";
    if ($result->errorMessage) {
        echo "Error: {$result->errorMessage}\n";
    }
    echo "\n";
    
    foreach ($result->tours as $i => $raw) {
        $tour = $raw['tour'] ?? [];
        $deps = $raw['departure'] ?? [];
        
        echo "── Tour " . ($i + 1) . " ──\n";
        echo "  code     : " . ($tour['wholesaler_tour_code'] ?? '?') . "\n";
        echo "  title    : " . ($tour['title'] ?? '?') . "\n";
        echo "  ext_id   : " . ($tour['external_id'] ?? '?') . "\n";
        echo "  days     : " . ($tour['duration_days'] ?? '?') . " / nights: " . ($tour['duration_nights'] ?? '?') . "\n";
        echo "  hotel★   : " . ($tour['hotel_star'] ?? '-') . "\n";
        echo "  transport: " . ($tour['transport_id'] ?? '-') . "\n";
        echo "  cover    : " . (isset($tour['cover_image_url']) ? substr($tour['cover_image_url'], 0, 80) . '...' : '-') . "\n";
        echo "  pdf      : " . (isset($tour['pdf_url']) ? substr($tour['pdf_url'], 0, 80) . '...' : '-') . "\n";
        echo "  min_price: " . number_format($tour['min_price'] ?? 0) . "\n";
        echo "  price_adult: " . number_format($tour['price_adult'] ?? 0) . "\n";
        echo "  departures: " . count($deps) . "\n";
        
        // Show itinerary
        $itins = $raw['itinerary'] ?? [];
        echo "  itinerary: " . count($itins) . " days\n";
        foreach (array_slice($itins, 0, 2) as $it) {
            echo "    Day {$it['day']}: " . mb_substr($it['description'] ?? '', 0, 60) . "...\n";
        }
        
        foreach (array_slice($deps, 0, 3) as $j => $dep) {
            echo "    [{$j}] {$dep['start_date']} → {$dep['end_date']}  " .
                 "฿" . number_format($dep['price_adult'] ?? 0) .
                 "  avail:{$dep['available']}/{$dep['capacity']}" .
                 "  status:{$dep['status']}" .
                 ($dep['commission_agent'] ? "  comm:" . number_format($dep['commission_agent']) : '') .
                 "\n";
        }
        if (count($deps) > 3) {
            echo "    ... +" . (count($deps) - 3) . " more\n";
        }
        echo "\n";
    }
} catch (\Throwable $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
}
