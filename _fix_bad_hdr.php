$ids = [22, 24];

foreach ($ids as $id) {
    $c = \App\Models\WholesalerApiConfig::find($id);
    if (!$c) {
        echo "config {$id}: NOT FOUND\n";
        continue;
    }

    $w = $c->wholesaler;
    $before = [
        'auth_type' => $c->auth_type,
        'auth_credentials' => $c->auth_credentials,
    ];

    $c->auth_credentials = null;
    $c->save();

    // reload to verify
    $fresh = \App\Models\WholesalerApiConfig::find($id);

    echo "── config {$id}  wholesaler_id={$c->wholesaler_id}  ({$w->name})\n";
    echo "  BEFORE  auth_type={$before['auth_type']}  auth_credentials=" . json_encode($before['auth_credentials'], JSON_UNESCAPED_UNICODE) . "\n";
    echo "  AFTER   auth_type={$fresh->auth_type}  auth_credentials=" . json_encode($fresh->auth_credentials, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\nDone.\n";
