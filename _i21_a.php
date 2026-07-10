$c = App\Models\WholesalerApiConfig::with('wholesaler')->find(21);
if (!$c) { echo "NOT_FOUND\n"; return; }
$out = $c->toArray();
unset($out['auth_credentials'], $out['booking_config']);
$cred = $c->auth_credentials ?? [];
$out['_credentials_summary'] = [
    'endpoints' => $cred['endpoints'] ?? null,
    'pagination' => $cred['pagination'] ?? null,
    'periods_match_key' => $cred['periods_match_key'] ?? null,
    'periods_tour_key' => $cred['periods_tour_key'] ?? null,
    'two_phase_sync' => $cred['two_phase_sync'] ?? null,
    'auth_headers_keys' => isset($cred['headers']) ? array_keys($cred['headers']) : [],
    'has_client_id' => isset($cred['client_id']),
    'has_oauth_fields' => isset($cred['oauth_fields']),
];
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
