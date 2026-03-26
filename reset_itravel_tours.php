<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deleted = \App\Models\Tour::where('wholesaler_id', 35)->delete();
echo "Deleted tours for wholesaler 35: $deleted\n";
