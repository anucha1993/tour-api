<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;
use App\Models\GalleryImage;

// Test with the known active tour
$slug = 'nt202602001';
$tour = Tour::where('slug', $slug)->where('status', 'active')->first();

if (!$tour) {
    echo "Tour not found!\n";
    exit;
}

echo "Tour found: {$tour->title}\n";
echo "Hashtags type: " . gettype($tour->hashtags) . "\n";
echo "Hashtags value: " . json_encode($tour->hashtags) . "\n";

// Test the hashtags cast
$hashtags = $tour->hashtags;
if (is_string($hashtags)) {
    echo "WARNING: hashtags is a string, not array\n";
    $hashtags = json_decode($hashtags, true);
    if (!is_array($hashtags)) {
        $hashtags = [$hashtags];
    }
}

if (!empty($hashtags) && is_array($hashtags)) {
    echo "Calling byTags with: " . json_encode($hashtags) . "\n";
    try {
        $images = GalleryImage::where('is_active', true)->byTags($hashtags)->count();
        echo "Gallery images found: $images\n";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "No hashtags, skipping gallery\n";
}

echo "\nDone.\n";
