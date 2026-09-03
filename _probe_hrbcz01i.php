$config = App\Models\WholesalerApiConfig::find(3);
$resp = Illuminate\Support\Facades\Http::withHeaders([
    'Accept' => 'application/json',
])->timeout(30)->get($config->api_base_url);

echo "status: " . $resp->status() . "\n";
$json = $resp->json();
if (is_array($json)) {
    echo "top-level keys: " . implode(', ', array_keys($json)) . "\n";
    foreach (['total','count','totalRecord','total_count','meta','pagination','last_page','totalPages'] as $k) {
        if (isset($json[$k])) {
            echo "$k => " . json_encode($json[$k]) . "\n";
        }
    }
    $data = $json['data'] ?? $json;
    if (is_array($data)) {
        echo "data count: " . count($data) . "\n";
    }
} else {
    echo "raw body (first 2000 chars): " . substr($resp->body(), 0, 2000) . "\n";
}
