<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Wholesaler;

// Simulate what the API does: created_at desc, page 1, 15 per page
$wholesalers = Wholesaler::withCount('tours')
    ->orderBy('created_at', 'desc')
    ->paginate(15);

echo "Page 1 of {$wholesalers->lastPage()} (total: {$wholesalers->total()})\n\n";
foreach ($wholesalers->items() as $w) {
    echo sprintf("%-6s %-45s tours_count=%d\n", $w->code, $w->name, $w->tours_count);
}
