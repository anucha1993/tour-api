<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TourReview;
use App\Models\Tour;

$reviews = TourReview::with('tour:id,tour_code,slug')->approved()->get();
echo "=== Approved Reviews ({$reviews->count()}) ===\n";
foreach ($reviews as $r) {
    echo "Review ID: {$r->id} | Tour: " . ($r->tour ? $r->tour->tour_code : 'N/A') . " | Rating: {$r->rating} | Tags: " . json_encode($r->tags, JSON_UNESCAPED_UNICODE) . " | Name: {$r->reviewer_name}\n";
    
    // Check tour's hashtags
    if ($r->tour) {
        $tour = Tour::find($r->tour_id);
        echo "  Tour hashtags: " . json_encode($tour->hashtags, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// Check tour NT202602037
echo "\n=== Tour NT202602037 ===\n";
$tour = Tour::where('tour_code', 'NT202602037')->first();
if ($tour) {
    echo "Hashtags: " . json_encode($tour->hashtags, JSON_UNESCAPED_UNICODE) . "\n";
    
    // Find reviews that share ANY tag with this tour's hashtags  
    $hashtags = $tour->hashtags;
    if (is_string($hashtags)) $hashtags = json_decode($hashtags, true);
    if (!is_array($hashtags)) $hashtags = [];
    
    echo "Looking for reviews with matching tags...\n";
    if (!empty($hashtags)) {
        $matchedReviews = TourReview::approved()
            ->where(function($q) use ($hashtags) {
                foreach ($hashtags as $tag) {
                    $q->orWhereRaw("JSON_SEARCH(tags, 'one', ?) IS NOT NULL", [$tag]);
                }
            })
            ->get();
        echo "Matched by review tags: {$matchedReviews->count()}\n";
        foreach ($matchedReviews as $mr) {
            echo "  - ID: {$mr->id} | Tags: " . json_encode($mr->tags, JSON_UNESCAPED_UNICODE) . " | Rating: {$mr->rating}\n";
        }
    }
    
    // Also find reviews from tours that share hashtags
    echo "\nLooking for reviews from tours with matching hashtags...\n";
    $matchingTourIds = Tour::where('status', 'active')
        ->where(function($q) use ($hashtags) {
            foreach ($hashtags as $tag) {
                $q->orWhereRaw("JSON_SEARCH(hashtags, 'one', ?) IS NOT NULL", [$tag]);
            }
        })
        ->pluck('id')
        ->toArray();
    echo "Tours with matching hashtags: " . json_encode($matchingTourIds) . "\n";
    
    $reviewsFromMatchingTours = TourReview::approved()
        ->whereIn('tour_id', $matchingTourIds)
        ->get();
    echo "Reviews from matching tours: {$reviewsFromMatchingTours->count()}\n";
    foreach ($reviewsFromMatchingTours as $mr) {
        echo "  - ID: {$mr->id} | Tour ID: {$mr->tour_id} | Rating: {$mr->rating} | Tags: " . json_encode($mr->tags, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
