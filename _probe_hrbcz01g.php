$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(3);
$result = $adapter->fetchTours(null);
echo "count: " . count($result->tours) . "\n";
echo "First item structure:\n";
echo json_encode($result->tours[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
