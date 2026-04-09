<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== PERIODS TABLE INDEXES ===\n";
$indexes = DB::select('SHOW INDEX FROM periods');
foreach ($indexes as $i) {
    echo "{$i->Key_name} : {$i->Column_name} (seq:{$i->Seq_in_index})\n";
}

echo "\n=== TOURS TABLE INDEXES ===\n";
$indexes = DB::select('SHOW INDEX FROM tours');
foreach ($indexes as $i) {
    echo "{$i->Key_name} : {$i->Column_name} (seq:{$i->Seq_in_index})\n";
}

echo "\n=== PERIODS COUNT ===\n";
echo DB::table('periods')->count() . "\n";

echo "\n=== TOURS COUNT ===\n";
echo DB::table('tours')->count() . "\n";
