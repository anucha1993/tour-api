$config = App\Models\WholesalerApiConfig::find(3);
echo "=== Integration 3 config ===\n";
echo json_encode([
    'id' => $config->id,
    'wholesaler_id' => $config->wholesaler_id,
    'wholesaler_name' => $config->wholesaler?->name,
    'sync_method' => $config->sync_method,
    'sync_mode' => $config->sync_mode,
    'api_base_url' => $config->api_base_url,
    'is_active' => $config->is_active,
    'last_synced_at' => $config->last_synced_at,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== Tour TF-HRBCZ01 in DB ===\n";
$tour = App\Models\Tour::where('tour_code', 'TF-HRBCZ01')->first();
if ($tour) {
    echo json_encode($tour->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "NOT FOUND in tours table\n";
}

echo "\n=== Recent sync logs for integration 3 ===\n";
$logs = App\Models\SyncLog::where('wholesaler_api_config_id', 3)->orderByDesc('id')->limit(5)->get();
foreach ($logs as $l) {
    echo json_encode($l->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n";
}

echo "\n=== Sync error logs mentioning HRBCZ01 ===\n";
$errs = App\Models\SyncErrorLog::where('wholesaler_api_config_id', 3)
    ->where(function($q) {
        $q->where('raw_data', 'like', '%HRBCZ01%')
          ->orWhere('error_message', 'like', '%HRBCZ01%')
          ->orWhere('tour_code', 'like', '%HRBCZ01%');
    })
    ->orderByDesc('id')->limit(10)->get();
foreach ($errs as $e) {
    echo json_encode($e->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n";
}
if ($errs->isEmpty()) {
    echo "none found\n";
}
