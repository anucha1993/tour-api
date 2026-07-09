<?php
/**
 * Cleanup outbound_api_logs — keep only N latest rows per (wholesaler_id, action).
 *
 * Usage:
 *   php cleanup_outbound_api_logs.php                # keep=7, execute
 *   php cleanup_outbound_api_logs.php --keep=7       # explicit keep count
 *   php cleanup_outbound_api_logs.php --dry-run      # preview only, no delete
 *   php cleanup_outbound_api_logs.php --keep=10 --dry-run
 *   php cleanup_outbound_api_logs.php --no-optimize  # skip OPTIMIZE TABLE at end
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// ---- Parse args ----
$args = $argv;
$keep       = 7;
$dryRun     = false;
$doOptimize = true;
$batchSize  = 5000;

foreach ($args as $a) {
    if (preg_match('/^--keep=(\d+)$/', $a, $m))   $keep = (int) $m[1];
    if ($a === '--dry-run')                       $dryRun = true;
    if ($a === '--no-optimize')                   $doOptimize = false;
    if (preg_match('/^--batch=(\d+)$/', $a, $m))  $batchSize = (int) $m[1];
}
if ($keep < 1) $keep = 1;

echo "==========================================\n";
echo " Cleanup outbound_api_logs\n";
echo " Keep latest : {$keep} per (wholesaler_id, action)\n";
echo " Batch size  : {$batchSize}\n";
echo " Mode        : " . ($dryRun ? 'DRY-RUN (no delete)' : 'EXECUTE') . "\n";
echo " Optimize    : " . ($doOptimize ? 'yes' : 'no') . "\n";
echo "==========================================\n\n";

// ---- Baseline size ----
$totalBefore = (int) DB::table('outbound_api_logs')->count();
$sizeRow = DB::selectOne("
    SELECT ROUND((data_length + index_length) / 1024 / 1024, 1) AS mb
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'outbound_api_logs'
");
$sizeBefore = $sizeRow->mb ?? 0;
echo "Current rows : " . number_format($totalBefore) . "\n";
echo "Current size : {$sizeBefore} MB\n\n";

// ---- Build list of IDs to KEEP (top N per group) ----
echo "Scanning groups (wholesaler_id, action) ...\n";
$groups = DB::table('outbound_api_logs')
    ->select('wholesaler_id', 'action', DB::raw('COUNT(*) AS cnt'))
    ->groupBy('wholesaler_id', 'action')
    ->get();

echo "Found " . $groups->count() . " groups.\n\n";

$totalKeepIds = [];
$plannedDelete = 0;

foreach ($groups as $g) {
    if ($g->cnt <= $keep) {
        // Group is already small enough — keep all
        continue;
    }
    // Get IDs of latest N in this group
    $keepIds = DB::table('outbound_api_logs')
        ->where('wholesaler_id', $g->wholesaler_id)
        ->where('action', $g->action)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit($keep)
        ->pluck('id')
        ->all();

    // Delete count in this group = total - kept
    $plannedDelete += ($g->cnt - count($keepIds));

    // For batch delete we need the IDs to keep across all groups
    foreach ($keepIds as $id) $totalKeepIds[$id] = true;
}

echo "Rows to delete : " . number_format($plannedDelete) . "\n";
echo "Rows to keep   : " . number_format(count($totalKeepIds)) . "\n\n";

if ($dryRun) {
    echo "[DRY-RUN] Not deleting. Re-run without --dry-run to execute.\n";
    exit(0);
}

if ($plannedDelete === 0) {
    echo "Nothing to delete. Done.\n";
    exit(0);
}

// ---- Delete in batches ----
// Strategy: iterate over each group and delete all rows whose id NOT IN keepIds for that group.
// Doing it per-group keeps the NOT IN list small and lets us report progress cleanly.
echo "Deleting ...\n";
$start = microtime(true);
$deletedTotal = 0;

foreach ($groups as $g) {
    if ($g->cnt <= $keep) continue;

    $keepIds = DB::table('outbound_api_logs')
        ->where('wholesaler_id', $g->wholesaler_id)
        ->where('action', $g->action)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit($keep)
        ->pluck('id')
        ->all();

    // Delete in chunks to avoid huge locks / binlog
    do {
        $affected = DB::table('outbound_api_logs')
            ->where('wholesaler_id', $g->wholesaler_id)
            ->where('action', $g->action)
            ->whereNotIn('id', $keepIds)
            ->limit($batchSize)
            ->delete();
        $deletedTotal += $affected;
        if ($affected > 0) {
            echo "  ws={$g->wholesaler_id} action={$g->action}  deleted +{$affected}  (total " . number_format($deletedTotal) . ")\n";
        }
    } while ($affected === $batchSize);
}

$elapsed = round(microtime(true) - $start, 1);
echo "\nDeleted {$deletedTotal} rows in {$elapsed}s.\n\n";

// ---- Optimize to reclaim disk ----
if ($doOptimize) {
    echo "Running OPTIMIZE TABLE outbound_api_logs (may take a while) ...\n";
    $t0 = microtime(true);
    DB::statement('OPTIMIZE TABLE outbound_api_logs');
    echo "Optimized in " . round(microtime(true) - $t0, 1) . "s.\n\n";
}

// ---- Report ----
$totalAfter = (int) DB::table('outbound_api_logs')->count();
$sizeRow2 = DB::selectOne("
    SELECT ROUND((data_length + index_length) / 1024 / 1024, 1) AS mb
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'outbound_api_logs'
");
$sizeAfter = $sizeRow2->mb ?? 0;

echo "==========================================\n";
echo " Rows before  : " . number_format($totalBefore) . "\n";
echo " Rows after   : " . number_format($totalAfter) . "\n";
echo " Rows freed   : " . number_format($totalBefore - $totalAfter) . "\n";
echo " Size before  : {$sizeBefore} MB\n";
echo " Size after   : {$sizeAfter} MB\n";
echo " Size freed   : " . round($sizeBefore - $sizeAfter, 1) . " MB\n";
echo "==========================================\n";
