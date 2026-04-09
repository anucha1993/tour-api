<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TOURS TABLE COLUMNS ===\n";
$cols = DB::select('SHOW COLUMNS FROM tours');
foreach ($cols as $c) {
    echo "{$c->Field} ({$c->Type})\n";
}

echo "\n=== EXPLAIN the base query ===\n";
$explain = DB::select("EXPLAIN SELECT * FROM tours WHERE status = 'active' AND EXISTS (SELECT 1 FROM periods WHERE periods.tour_id = tours.id AND start_date >= CURDATE() AND status = 'open') AND (primary_country_id != 8 OR primary_country_id IS NULL) ORDER BY COALESCE(view_count, 0) DESC, created_at DESC LIMIT 10");
foreach ($explain as $e) {
    echo json_encode($e, JSON_PRETTY_PRINT) . "\n";
}
