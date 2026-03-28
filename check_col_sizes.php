<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['title','meta_title','meta_description','keywords','hashtags','highlights'] as $f) {
    $col = DB::select("SHOW COLUMNS FROM tours WHERE Field = ?", [$f]);
    echo $f . ': ' . ($col[0]->Type ?? 'NOT_FOUND') . "\n";
}
