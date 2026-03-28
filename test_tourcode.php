<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;

$prefix = 'NT';
$yearMonth = now()->format('Ym');
$basePattern = $prefix . $yearMonth;
$prefixLen = strlen($basePattern);

$maxSeq = (int) Tour::where('tour_code', 'like', "{$basePattern}%")
    ->selectRaw("MAX(CAST(SUBSTRING(tour_code, ?) AS UNSIGNED)) as max_seq", [$prefixLen + 1])
    ->value('max_seq');

echo "Base pattern: {$basePattern}\n";
echo "Max seq (numeric): {$maxSeq}\n";
echo "Next code: {$basePattern}" . str_pad($maxSeq + 1, 4, '0', STR_PAD_LEFT) . "\n";

// Verify it doesn't exist
$nextCode = $basePattern . str_pad($maxSeq + 1, 4, '0', STR_PAD_LEFT);
$exists = Tour::where('tour_code', $nextCode)->exists();
echo "Next code exists: " . ($exists ? 'YES (BUG!)' : 'NO (good)') . "\n";
