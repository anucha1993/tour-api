$c = App\Models\WholesalerApiConfig::find(21);
$cred = $c->auth_credentials ?? [];

// Fetch tours via adapter
$adapter = App\Services\WholesalerAdapters\AdapterFactory::create(39);
$result = $adapter->fetchTours(null);
echo "success=" . ($result->success ? 'true' : 'false') . "\n";
echo "err=" . ($result->errorMessage ?? '') . "\n";
echo "count=" . count($result->tours ?? []) . "\n";
echo "hasMore=" . ($result->hasMore ? 'true' : 'false') . "\n";

if (!empty($result->tours)) {
    echo "\nFirst tour keys: " . implode(',', array_keys($result->tours[0])) . "\n";
    // list all tour codes
    echo "\n=== All tour codes from API ===\n";
    foreach($result->tours as $i => $t) {
        $code = $t['code'] ?? $t['program_code'] ?? $t['tour_code'] ?? $t['wholesaler_tour_code'] ?? '?';
        $id = $t['id'] ?? $t['program_id'] ?? $t['external_id'] ?? '?';
        $name = mb_substr($t['name'] ?? $t['title'] ?? '',0,60);
        echo sprintf(" [%d] id=%s code=%s name=%s\n", $i, $id, $code, $name);
    }
}
