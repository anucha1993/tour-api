<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Active Tours ===\n";
$tours = App\Models\Tour::where('status', 'active')->select('id', 'slug', 'title', 'is_published')->get();
foreach ($tours as $tour) {
    echo $tour->id . ' | ' . ($tour->slug ?: 'NO-SLUG') . ' | pub=' . ($tour->is_published ? 'Y' : 'N') . ' | ' . mb_substr($tour->title, 0, 60) . "\n";
}

echo "\n=== Test API endpoint ===\n";
if ($tours->count() > 0) {
    $firstSlug = $tours->first()->slug;
    if ($firstSlug) {
        $tour = App\Models\Tour::where('slug', $firstSlug)
            ->where('status', 'active')
            ->with(['gallery', 'periods'])
            ->first();
        echo "Found tour: " . ($tour ? 'YES' : 'NO') . "\n";
        echo "Gallery count: " . ($tour ? $tour->gallery->count() : 0) . "\n";
        echo "Periods count: " . ($tour ? $tour->periods->count() : 0) . "\n";
    }
}
