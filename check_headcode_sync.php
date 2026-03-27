<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = \App\Models\WholesalerApiConfig::find(22);
echo "=== Integration 22 (TTN Japan / Headcode) ===\n";
echo "wholesaler_id: {$c->wholesaler_id}\n";
echo "integration_type: {$c->integration_type}\n";
echo "headcode_file: " . ($c->headcode_file ?? 'NULL') . "\n";
echo "sync_enabled: " . ($c->sync_enabled ? 'YES' : 'NO') . "\n";
echo "sync_schedule: " . ($c->sync_schedule ?? 'NULL') . "\n";
echo "full_sync_schedule: " . ($c->full_sync_schedule ?? 'NULL') . "\n";
echo "sync_limit: " . ($c->sync_limit ?? 'NULL') . "\n";
echo "is_active: " . ($c->is_active ? 'YES' : 'NO') . "\n\n";

// Recent sync logs
$logs = \App\Models\SyncLog::where('wholesaler_id', $c->wholesaler_id)
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get();

echo "=== Recent Sync Logs (wholesaler_id={$c->wholesaler_id}) ===\n";
if ($logs->isEmpty()) {
    echo "No sync logs found!\n";
} else {
    foreach ($logs as $log) {
        $duration = $log->completed_at && $log->created_at 
            ? \Carbon\Carbon::parse($log->created_at)->diffInSeconds(\Carbon\Carbon::parse($log->completed_at)) . 's'
            : '?';
        echo "[{$log->id}] {$log->created_at} | {$log->status} | {$log->sync_type} | {$duration}\n";
        echo "  received={$log->tours_received} created={$log->tours_created} updated={$log->tours_updated} skipped={$log->tours_skipped}\n";
        if ($log->error_message) echo "  error: " . mb_substr($log->error_message, 0, 150) . "\n";
        echo "---\n";
    }
}

// Check SyncCursor
$cursor = \App\Models\SyncCursor::where('wholesaler_id', $c->wholesaler_id)->first();
echo "\n=== SyncCursor ===\n";
if ($cursor) {
    echo "cursor_value: " . ($cursor->cursor_value ?? 'NULL') . "\n";
    echo "last_synced_at: " . ($cursor->last_synced_at ?? 'NULL') . "\n";
} else {
    echo "No cursor record\n";
}

// Check cron schedule parsing
echo "\n=== Cron Schedule Check ===\n";
$schedule = $c->sync_schedule;
if ($schedule) {
    try {
        $cron = new \Cron\CronExpression($schedule);
        $now = new \DateTime();
        $next = $cron->getNextRunDate($now);
        $prev = $cron->getPreviousRunDate($now);
        echo "Schedule: {$schedule}\n";
        echo "Previous run: {$prev->format('Y-m-d H:i:s')}\n";
        echo "Next run: {$next->format('Y-m-d H:i:s')}\n";
        echo "Is due now? " . ($cron->isDue() ? 'YES' : 'NO') . "\n";
    } catch (\Throwable $e) {
        echo "Invalid cron: {$e->getMessage()}\n";
    }
} else {
    echo "NO sync_schedule set! Scheduled sync will NOT run.\n";
}

$fullSchedule = $c->full_sync_schedule;
if ($fullSchedule) {
    try {
        $cron = new \Cron\CronExpression($fullSchedule);
        $next = $cron->getNextRunDate(new \DateTime());
        echo "\nFull sync schedule: {$fullSchedule}\n";
        echo "Next full sync: {$next->format('Y-m-d H:i:s')}\n";
    } catch (\Throwable $e) {
        echo "\nFull sync invalid cron: {$e->getMessage()}\n";
    }
} else {
    echo "\nNO full_sync_schedule set.\n";
}

// Tour count
$tourCount = \App\Models\Tour::where('wholesaler_id', $c->wholesaler_id)->count();
$periodCount = \App\Models\Period::whereHas('tour', fn($q) => $q->where('wholesaler_id', $c->wholesaler_id))->count();
echo "\n=== DB Stats ===\n";
echo "Tours: {$tourCount}\n";
echo "Periods: {$periodCount}\n";
