<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- jobs table (pending) ---\n";
foreach (DB::table('jobs')->orderBy('id')->get() as $j) {
    $payload = json_decode($j->payload, true);
    echo $j->id . ' | queue=' . $j->queue
        . ' | attempts=' . $j->attempts
        . ' | class=' . ($payload['displayName'] ?? '?')
        . ' | created=' . date('Y-m-d H:i:s', $j->created_at)
        . ' | available_at=' . date('Y-m-d H:i:s', $j->available_at)
        . PHP_EOL;
}

echo "\n--- failed_jobs (last 20) ---\n";
foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(20)->get() as $f) {
    $payload = json_decode($f->payload, true);
    echo $f->id . ' | ' . ($payload['displayName'] ?? '?') . ' | failed_at=' . $f->failed_at . PHP_EOL;
    if (stripos($payload['displayName'] ?? '', 'Invoice') !== false) {
        echo "  exception: " . mb_substr($f->exception, 0, 800) . "\n";
    }
}
