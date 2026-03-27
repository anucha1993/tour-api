<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Wholesaler;

$wholesalers = Wholesaler::withCount('tours')->orderBy('tours_count', 'desc')->get();
foreach ($wholesalers as $w) {
    echo sprintf("%-6s %-40s => %d tours\n", $w->code, $w->name, $w->tours_count);
}
echo "\nTotal wholesalers: " . $wholesalers->count() . PHP_EOL;
echo "With tours > 0: " . $wholesalers->where('tours_count', '>', 0)->count() . PHP_EOL;
