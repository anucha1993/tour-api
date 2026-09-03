$wholesalerId = 3;

echo "=== Recent sync logs for wholesaler 3 (Tour Factory) ===\n";
$logs = App\Models\SyncLog::where('wholesaler_id', $wholesalerId)->orderByDesc('id')->limit(5)->get();
foreach ($logs as $l) {
    echo json_encode($l->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n";
}
if ($logs->isEmpty()) echo "NO sync logs at all for wholesaler 3\n";

echo "\n=== Sync error logs mentioning HRBCZ01 (wholesaler 3) ===\n";
$errs = App\Models\SyncErrorLog::where('wholesaler_id', $wholesalerId)
    ->where(function($q) {
        $q->where('external_tour_code', 'like', '%HRBCZ01%')
          ->orWhere('error_message', 'like', '%HRBCZ01%')
          ->orWhere('raw_data', 'like', '%HRBCZ01%');
    })
    ->orderByDesc('id')->limit(10)->get();
foreach ($errs as $e) {
    echo json_encode($e->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n";
}
if ($errs->isEmpty()) echo "none found\n";

echo "\n=== All recent sync_error_logs for wholesaler 3 (last 10, any tour) ===\n";
$errs2 = App\Models\SyncErrorLog::where('wholesaler_id', $wholesalerId)->orderByDesc('id')->limit(10)->get();
foreach ($errs2 as $e) {
    echo $e->id . " | " . $e->external_tour_code . " | " . $e->error_type . " | " . $e->error_message . " | created=" . $e->created_at . "\n";
}
