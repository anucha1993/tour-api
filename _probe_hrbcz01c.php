$config = App\Models\WholesalerApiConfig::find(3);
echo "auth_type: " . $config->auth_type . "\n";
echo "auth_credentials: " . json_encode($config->auth_credentials, JSON_PRETTY_PRINT) . "\n";
echo "endpoint config (raw attrs check):\n";
$attrs = $config->getAttributes();
foreach (['api_base_url','sync_method','sync_mode','field_mappings_version'] as $k) {
    echo "$k => " . ($attrs[$k] ?? 'N/A') . "\n";
}
