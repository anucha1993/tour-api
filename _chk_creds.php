$c22 = \App\Models\WholesalerApiConfig::find(22);
$c24 = \App\Models\WholesalerApiConfig::find(24);

echo "config 22: auth_type={$c22->auth_type}  creds=" . json_encode($c22->auth_credentials, JSON_UNESCAPED_UNICODE) . "\n";
echo "config 24: auth_type={$c24->auth_type}  creds=" . json_encode($c24->auth_credentials, JSON_UNESCAPED_UNICODE) . "\n";
