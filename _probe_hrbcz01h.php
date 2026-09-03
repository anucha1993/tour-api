$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(3);
$result = $adapter->fetchTours(null);
echo "count: " . count($result->tours) . "\n";
$codes = array_map(fn($t) => $t['code'] ?? null, $result->tours);
echo "codes: " . implode(', ', $codes) . "\n";
$found = false;
foreach ($codes as $c) {
    if (is_string($c) && stripos($c, 'HRBCZ01') !== false) { $found = true; }
}
echo "Contains HRBCZ01: " . ($found ? 'YES' : 'NO') . "\n";
