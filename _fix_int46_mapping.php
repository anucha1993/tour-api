<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use Illuminate\Support\Facades\DB;

$configId = 46;
$wsId = 54;

$config = WholesalerApiConfig::findOrFail($configId);

// ============================================================
// 1) Set endpoints.periods for Two-Phase Sync
// ============================================================
$creds = $config->auth_credentials ?? [];
$creds['endpoints'] = $creds['endpoints'] ?? [];
$creds['endpoints']['periods'] = 'https://api-formosa.ht1freshdigital.com/wp-json/bs-api/v1/tour-dates?tour_id={tour_id}';
$config->auth_credentials = $creds;
$config->sync_mode = 'two_phase';
$config->save();
echo "✓ endpoints.periods set → {$creds['endpoints']['periods']}\n";
echo "✓ sync_mode = two_phase\n\n";

// ============================================================
// 2) Fix departure.start_date  (period → date_range, part=start)
// ============================================================
$startMap = WholesalerFieldMapping::where('wholesaler_id', $wsId)
    ->where('section_name', 'departure')
    ->where('our_field', 'start_date')
    ->first();
if ($startMap) {
    $startMap->their_field       = 'period';
    $startMap->their_field_path  = 'period';
    $startMap->transform_type    = 'custom';
    $startMap->transform_config  = ['operation' => 'date_range', 'part' => 'start'];
    $startMap->is_active         = 1;
    $startMap->save();
    echo "✓ start_date mapping updated (id={$startMap->id})\n";
} else {
    echo "✗ start_date mapping not found!\n";
}

// ============================================================
// 3) Fix departure.end_date  (period → date_range, part=end)
// ============================================================
$endMap = WholesalerFieldMapping::where('wholesaler_id', $wsId)
    ->where('section_name', 'departure')
    ->where('our_field', 'end_date')
    ->first();
if ($endMap) {
    $endMap->their_field       = 'period';
    $endMap->their_field_path  = 'period';
    $endMap->transform_type    = 'custom';
    $endMap->transform_config  = ['operation' => 'date_range', 'part' => 'end'];
    $endMap->is_active         = 1;
    $endMap->save();
    echo "✓ end_date mapping updated (id={$endMap->id})\n";
} else {
    echo "✗ end_date mapping not found!\n";
}

// ============================================================
// 4) Add/Update departure.status  (post_status → lookup → open/closed)
// ============================================================
$statusMap = WholesalerFieldMapping::firstOrNew([
    'wholesaler_id' => $wsId,
    'section_name'  => 'departure',
    'our_field'     => 'status',
]);
$statusMap->their_field       = 'post_status';
$statusMap->their_field_path  = 'post_status';
$statusMap->transform_type    = 'value_map';
$statusMap->transform_config  = [
    'map' => [
        'publish' => 'open',
        'draft'   => 'closed',
        'private' => 'closed',
        'pending' => 'closed',
        'trash'   => 'cancelled',
    ],
    'default' => 'open',
];
$statusMap->default_value = 'open';
$statusMap->is_active     = 1;
$statusMap->save();
echo "✓ status mapping upserted (id={$statusMap->id})\n";

// ============================================================
// 5) Show final departure mappings
// ============================================================
echo "\n=== Final departure mappings ===\n";
$rows = WholesalerFieldMapping::where('wholesaler_id', $wsId)
    ->where('section_name', 'departure')
    ->where('is_active', 1)
    ->orderBy('our_field')->get();
foreach ($rows as $r) {
    $t = $r->transform_type ?? 'direct';
    $cfg = $r->transform_config ? json_encode($r->transform_config, JSON_UNESCAPED_UNICODE) : '';
    echo sprintf("  %-20s <- %-20s [%s] %s\n", $r->our_field, $r->their_field_path ?? $r->their_field, $t, $cfg);
}

echo "\n=== Config state ===\n";
$config->refresh();
echo "  sync_mode: {$config->sync_mode}\n";
echo "  is_active: {$config->is_active}  (keep 0 until we verify)\n";
echo "  endpoints.periods: ".($config->auth_credentials['endpoints']['periods'] ?? '(none)')."\n";
