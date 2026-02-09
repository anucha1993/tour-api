<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tour;
use App\Services\CloudflareImagesService;

echo "=== Checking Cloudflare Image Upload for Integration 5 ===\n\n";

// Check Cloudflare configuration
$cloudflare = app(CloudflareImagesService::class);
echo "1. Cloudflare configured: " . ($cloudflare->isConfigured() ? "YES" : "NO") . "\n";

// Get a tour from integration 5
$tour = Tour::where('wholesaler_id', 5)->whereNotNull('cover_image_url')->first();

if (!$tour) {
    echo "No tour found with cover_image_url from integration 5\n";
    exit;
}

echo "2. Tour: {$tour->title}\n";
echo "3. cover_image_url: {$tour->cover_image_url}\n";
echo "4. URL starts with http: " . (str_starts_with($tour->cover_image_url, 'http') ? 'YES' : 'NO') . "\n";
echo "5. Contains imagedelivery.net: " . (str_contains($tour->cover_image_url, 'imagedelivery.net') ? 'YES' : 'NO') . "\n";

// Check all conditions
$shouldUpload = $tour->cover_image_url 
    && str_starts_with($tour->cover_image_url, 'http') 
    && !str_contains($tour->cover_image_url, 'imagedelivery.net');
    
echo "6. Should upload to Cloudflare: " . ($shouldUpload ? 'YES' : 'NO') . "\n\n";

// Try to actually upload
if ($shouldUpload && $cloudflare->isConfigured()) {
    echo "7. Attempting upload...\n";
    try {
        $result = $cloudflare->uploadFromUrl($tour->cover_image_url, 'test-tour-cover-' . uniqid());
        if ($result) {
            echo "   SUCCESS! Result:\n";
            print_r($result);
        } else {
            echo "   FAILED: Upload returned null\n";
        }
    } catch (\Exception $e) {
        echo "   ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "7. Skipping upload - conditions not met\n";
}
