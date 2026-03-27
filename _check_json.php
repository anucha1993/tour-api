<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Wholesaler;

$wholesalers = Wholesaler::withCount('tours')
    ->orderBy('created_at', 'desc')
    ->paginate(3);

// Simulate exact JSON output
$json = json_encode([
    'success' => true,
    'data' => $wholesalers->items(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

echo $json;
