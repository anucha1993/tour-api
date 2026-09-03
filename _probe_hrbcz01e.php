use App\Services\WholesalerAdapters\AdapterFactory;

$adapter = AdapterFactory::create(3);
$result = $adapter->fetchTours(null);

echo "hasMore: " . var_export($result->hasMore, true) . "\n";
echo "nextCursor: " . var_export($result->nextCursor, true) . "\n";
echo "count(tours): " . count($result->tours) . "\n";

$found = null;
$codes = [];
foreach ($result->tours as $t) {
    $code = $t['tour']['wholesaler_tour_code'] ?? $t['tour']['external_id'] ?? null;
    $codes[] = $code;
    if (is_string($code) && stripos($code, 'HRBCZ01') !== false) {
        $found = $t;
    }
}
echo "All codes count: " . count($codes) . "\n";
echo "codes sample: " . implode(', ', array_slice($codes, 0, 20)) . "\n";
echo "Contains HRBCZ01 anywhere in codes: " . (in_array(true, array_map(fn($c) => is_string($c) && stripos($c,'HRBCZ01')!==false, $codes)) ? 'YES' : 'NO') . "\n";

if ($found) {
    echo "FOUND RAW:\n" . json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "TF-HRBCZ01 NOT in this batch of " . count($codes) . " tours\n";
}
