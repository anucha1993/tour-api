$c = App\Models\WholesalerApiConfig::find(21);
$cred = $c->auth_credentials ?? [];

echo "api_base_url: {$c->api_base_url}\n";
echo "endpoints: " . json_encode($cred['endpoints'] ?? null) . "\n";
echo "pagination: " . json_encode($cred['pagination'] ?? null) . "\n";
echo "auth_type: {$c->auth_type}\n";
echo "auth_header_name: {$c->auth_header_name}\n";
echo "headers: " . json_encode($cred['headers'] ?? null) . "\n";
echo "keys of cred: " . implode(', ', array_keys($cred)) . "\n";

// Direct HTTP call to see raw response
echo "\n=== Direct GET to base URL ===\n";
$ch = curl_init($c->api_base_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>30,
    CURLOPT_SSL_VERIFYPEER=>false,
]);
if (!empty($cred['headers'])) {
    $hdrs=[]; foreach($cred['headers'] as $k=>$v) $hdrs[]="{$k}: {$v}";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
}
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP {$code}\n";
echo "Body length: " . strlen($body) . "\n";
echo "First 500 chars: " . mb_substr($body,0,500) . "\n";

// Try to parse and count
$json = json_decode($body, true);
if (is_array($json)) {
    echo "\nTop-level keys: " . implode(',', array_keys($json)) . "\n";
    if (isset($json[0])) {
        echo "It's a list with " . count($json) . " items\n";
        if (is_array($json[0])) {
            echo "First item keys: " . implode(',', array_keys($json[0])) . "\n";
        }
    }
}
