<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Fix id=6075: primary_country_id ← country_code  (direct → lookup by iso2)
DB::table('wholesaler_field_mappings')
    ->where('id', 6075)
    ->update([
        'transform_type' => 'lookup',
        'transform_config' => json_encode([
            'lookup_table' => 'countries',
            'lookup_by'    => 'iso2',
        ]),
        'updated_at' => now(),
    ]);
echo "✅ Fixed id=6075 (primary_country_id): transform_type=lookup, lookup_by=iso2\n";

// Fix id=6076: transport_id ← airline_name  (direct → lookup by name)
DB::table('wholesaler_field_mappings')
    ->where('id', 6076)
    ->update([
        'transform_type' => 'lookup',
        'transform_config' => json_encode([
            'lookup_table' => 'transports',
            'lookup_by'    => 'name',
        ]),
        'updated_at' => now(),
    ]);
echo "✅ Fixed id=6076 (transport_id): transform_type=lookup, lookup_by=name\n";

// Verify
echo "\n=== Verified ===\n";
$rows = DB::table('wholesaler_field_mappings')
    ->whereIn('id', [6075, 6076])
    ->get(['id', 'our_field', 'their_field_path', 'transform_type', 'transform_config']);
foreach ($rows as $r) {
    echo "id={$r->id} | {$r->our_field} <- {$r->their_field_path}\n";
    echo "   transform_type   : {$r->transform_type}\n";
    echo "   transform_config : {$r->transform_config}\n";
}
