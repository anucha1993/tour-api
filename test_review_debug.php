<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\GalleryVideo;

// Find the tour
$tour = Tour::where('tour_code', 'NT202602037')
    ->with(['cities:id,name_en,name_th,country_id', 'primaryCountry:id,name_en,name_th'])
    ->first();

if (!$tour) {
    echo "Tour not found\n";
    exit;
}

echo "=== Tour: {$tour->tour_code} ===\n";
echo "Slug: {$tour->slug}\n";

// Check hashtags
$rawHashtags = $tour->getRawOriginal('hashtags');
echo "Raw hashtags from DB: {$rawHashtags}\n";
echo "Casted hashtags: " . json_encode($tour->hashtags, JSON_UNESCAPED_UNICODE) . "\n";
echo "Type: " . gettype($tour->hashtags) . "\n";

// Check cities
$cityNames = $tour->cities->pluck('name_th')->filter()->values()->toArray();
echo "City names: " . json_encode($cityNames, JSON_UNESCAPED_UNICODE) . "\n";

// Check country
$countryName = $tour->primaryCountry?->name_th;
echo "Country: {$countryName}\n";

// Build combined tags
$hashtags = $tour->hashtags;
if (is_string($hashtags)) $hashtags = json_decode($hashtags, true);
if (!is_array($hashtags)) $hashtags = [];

$allTags = array_values(array_unique(array_filter(
    array_merge($hashtags, $cityNames, $countryName ? [$countryName] : [])
)));
echo "Combined tags: " . json_encode($allTags, JSON_UNESCAPED_UNICODE) . "\n";

// Check all videos
$allVideos = GalleryVideo::all();
echo "\n=== All Videos ({$allVideos->count()}) ===\n";
foreach ($allVideos as $v) {
    $rawTags = $v->getRawOriginal('tags');
    echo "ID: {$v->id} | Active: {$v->is_active} | Raw tags: {$rawTags} | Casted: " . json_encode($v->tags, JSON_UNESCAPED_UNICODE) . " | Title: {$v->title}\n";
}

// Try matching
if (!empty($allTags)) {
    echo "\n=== Matching with byTags ===\n";
    $matched = GalleryVideo::active()->byTags($allTags)->get();
    echo "Matched: {$matched->count()}\n";
    foreach ($matched as $mv) {
        echo "  - ID: {$mv->id} | {$mv->title}\n";
    }
    
    // Also try raw SQL to debug
    echo "\n=== Raw SQL debug ===\n";
    foreach ($allTags as $tag) {
        $result = \Illuminate\Support\Facades\DB::select(
            "SELECT id, title, JSON_SEARCH(tags, 'one', ?) as search_result FROM gallery_videos WHERE is_active = 1",
            [$tag]
        );
        foreach ($result as $r) {
            echo "Tag '{$tag}' -> ID: {$r->id} search_result: " . ($r->search_result ?? 'NULL') . "\n";
        }
    }
}

// Simulate the actual controller method
echo "\n=== Simulating controller getGalleryVideosForTour ===\n";
$videos = GalleryVideo::active()
    ->byTags($allTags)
    ->inRandomOrder()
    ->limit(3)
    ->get();
echo "Result count: {$videos->count()}\n";
echo "Result: " . json_encode($videos->map(fn($v) => [
    'id' => $v->id,
    'video_url' => $v->video_url,
    'thumbnail_url' => $v->thumbnail_url,
    'title' => $v->title,
])->values()->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

// Test the actual API response
echo "\n=== Test public API tour detail gallery_videos field ===\n";
$controller = new \App\Http\Controllers\PublicTourController();
$reflection = new ReflectionMethod($controller, 'getGalleryVideosForTour');
$reflection->setAccessible(true);
$result = $reflection->invoke($controller, $tour);
echo "API result: " . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
