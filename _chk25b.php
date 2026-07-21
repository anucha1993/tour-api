$c = \App\Models\WholesalerApiConfig::find(25);
echo "columns: ".implode(", ", array_keys($c->getAttributes())).PHP_EOL.PHP_EOL;
echo "api_endpoint=".($c->api_endpoint ?? "NULL").PHP_EOL;
echo "base_url=".($c->base_url ?? "NULL").PHP_EOL;
echo PHP_EOL."=== full auth_credentials ===".PHP_EOL;
echo json_encode($c->auth_credentials, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
