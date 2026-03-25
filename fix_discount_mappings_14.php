<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Show exact rows for discount mappings
$rows = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 14)
    ->where('section_name', 'departure')
    ->whereIn('our_field', ['discount_adult', 'discount_child_bed', 'discount_child_nobed'])
    ->get();

echo "Current discount mappings:\n";
foreach ($rows as $r) {
    echo "id={$r->id} | {$r->our_field} | their_field='{$r->their_field}' | their_field_path='{$r->their_field_path}'\n";
}

// Fix: update both their_field and their_field_path
echo "\nFixing...\n";
foreach ($rows as $r) {
    switch ($r->our_field) {
        case 'discount_adult':
            $newPath = 'period[].discount_adult';
            break;
        case 'discount_child_bed':
            $newPath = 'period[].discount_child';
            break;
        case 'discount_child_nobed':
            $newPath = 'period[].discount_childno';
            break;
        default:
            continue 2;
    }
    DB::table('wholesaler_field_mappings')->where('id', $r->id)->update([
        'their_field'      => $newPath,
        'their_field_path' => $newPath,
        'updated_at'       => now(),
    ]);
    echo "Fixed id={$r->id} {$r->our_field} <- {$newPath}\n";
}

// Verify
echo "\nVerified:\n";
$rows2 = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 14)
    ->where('section_name', 'departure')
    ->whereIn('our_field', ['discount_adult', 'discount_child_bed', 'discount_child_nobed'])
    ->get();
foreach ($rows2 as $r) {
    echo "id={$r->id} | {$r->our_field} | their_field='{$r->their_field}' | their_field_path='{$r->their_field_path}'\n";
}
