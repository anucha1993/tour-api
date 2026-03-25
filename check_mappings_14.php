<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerFieldMapping;
use Illuminate\Support\Facades\DB;

echo "=== Mappings for wholesaler_id=14 ===\n";
$maps = WholesalerFieldMapping::where('wholesaler_id', 14)
    ->where('is_active', true)
    ->orderBy('section_name')
    ->orderBy('our_field')
    ->get();

foreach ($maps as $m) {
    $path = $m->their_field_path ?? $m->their_field ?? '';
    $type = $m->transform_type ?? 'direct';
    $config = $m->transform_config ? json_encode($m->transform_config, JSON_UNESCAPED_UNICODE) : '{}';
    echo "id={$m->id} | {$m->section_name}.{$m->our_field} <- {$path} | transform={$type}\n";
    if ($type !== 'direct' && $type !== null) {
        echo "   config: {$config}\n";
    }
}

// Also check what country codes the API gives vs what's in our DB
echo "\n=== Checking country ISO codes in DB ===\n";
$countries = DB::table('countries')->whereIn('iso2', ['HK', 'CN', 'JP', 'TH', 'TW', 'SG', 'KR'])->get(['id', 'iso2', 'iso3', 'name_en']);
foreach ($countries as $c) {
    echo "id={$c->id} | iso2={$c->iso2} | iso3={$c->iso3} | {$c->name_en}\n";
}
