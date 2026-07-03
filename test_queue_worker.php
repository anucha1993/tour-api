<?php
/**
 * ทดสอบว่า queue worker ทำงานได้จริงไหม
 * รันผ่าน CMD: "C:\Program Files (x86)\Plesk\Additional\PleskPHP82\php.exe" test_queue_worker.php
 * หรือผ่าน Plesk: Run a PHP script → api.nexttripholiday.com/test_queue_worker.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

echo "=== QUEUE WORKER TEST ===" . PHP_EOL . PHP_EOL;

// 1. Check pending jobs
$pending = DB::table('jobs')->count();
$failed = DB::table('failed_jobs')->count();
echo "1. Pending jobs: {$pending}" . PHP_EOL;
echo "   Failed jobs: {$failed}" . PHP_EOL . PHP_EOL;

// 2. Check jobs by queue
$queues = DB::table('jobs')->selectRaw('queue, count(*) as cnt')->groupBy('queue')->get();
echo "2. Jobs by queue:" . PHP_EOL;
foreach ($queues as $q) {
    echo "   - {$q->queue}: {$q->cnt}" . PHP_EOL;
}
echo PHP_EOL;

// 3. Check reserved (being processed) jobs
$reserved = DB::table('jobs')->whereNotNull('reserved_at')->count();
echo "3. Currently reserved (processing): {$reserved}" . PHP_EOL . PHP_EOL;

// 4. Check retry_after config
$retryAfter = config('queue.connections.database.retry_after');
echo "4. retry_after config: {$retryAfter} seconds" . PHP_EOL;
echo "   (ต้อง > 600 เพื่อรองรับ SyncToursJob timeout)" . PHP_EOL . PHP_EOL;

// 5. Try to process ONE job
echo "5. ลอง process 1 job..." . PHP_EOL;
try {
    $exitCode = Artisan::call('queue:work', [
        '--once' => true,
        'connection' => 'database',
    ]);
    $output = Artisan::output();
    echo "   Exit code: {$exitCode}" . PHP_EOL;
    echo "   Output: {$output}" . PHP_EOL;
} catch (\Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . PHP_EOL;
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

// 6. Check pending after
$pendingAfter = DB::table('jobs')->count();
echo "6. Pending jobs after: {$pendingAfter}" . PHP_EOL;
if ($pendingAfter < $pending) {
    echo "   ✅ Worker ทำงานได้! ลดจาก {$pending} → {$pendingAfter}" . PHP_EOL;
} else {
    echo "   ❌ Jobs ไม่ลด - worker อาจมีปัญหา" . PHP_EOL;
}

echo PHP_EOL . "=== DONE ===" . PHP_EOL;
