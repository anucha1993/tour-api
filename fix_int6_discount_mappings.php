<?php
/**
 * Fix Integration 6 discount mappings
 * - Wrap formulas with abs() to handle negative source values
 * - Add missing discount_adult mapping
 * - Convert discount_single from raw % to amount
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;

$cfg = WholesalerApiConfig::find(6);
if (!$cfg) { echo "Config 6 not found\n"; exit(1); }
$wid = $cfg->wholesaler_id;
echo "Wholesaler ID: {$wid}\n\n";

$updates = [
    // Wrap with abs() — source sends negative percent
    'discount_child_bed' => [
        'transform_type' => 'formula',
        'transform_config' => [
            'string_transform' => [
                'type' => 'formula',
                'formulaExpression' => 'abs({period_rate_adult_twn} * {period_promotion_group.discount} / 100)',
            ],
        ],
    ],
    'discount_child_nobed' => [
        'transform_type' => 'formula',
        'transform_config' => [
            'string_transform' => [
                'type' => 'formula',
                'formulaExpression' => 'abs({period_rate_adult_twn} * {period_promotion_group.discount} / 100)',
            ],
        ],
    ],
    // Compute single supplement discount as amount (was storing raw -2.5%)
    'discount_single' => [
        'transform_type' => 'formula',
        'transform_config' => [
            'string_transform' => [
                'type' => 'formula',
                'formulaExpression' => 'abs({period_rate_adult_sgl} * {period_promotion_group.discount} / 100)',
            ],
        ],
    ],
];

foreach ($updates as $field => $patch) {
    $m = WholesalerFieldMapping::where('wholesaler_id', $wid)
        ->where('section_name', 'departure')
        ->where('our_field', $field)
        ->first();
    if (!$m) {
        echo "  [SKIP] {$field}: mapping not found\n";
        continue;
    }
    $m->transform_type = $patch['transform_type'];
    $m->transform_config = $patch['transform_config'];
    $m->save();
    echo "  [OK]   {$field}: updated → {$patch['transform_config']['string_transform']['formulaExpression']}\n";
}

// Add discount_adult if missing
$existing = WholesalerFieldMapping::where('wholesaler_id', $wid)
    ->where('section_name', 'departure')
    ->where('our_field', 'discount_adult')
    ->first();

if (!$existing) {
    // Copy structure from discount_child_bed for consistency
    $template = WholesalerFieldMapping::where('wholesaler_id', $wid)
        ->where('section_name', 'departure')
        ->where('our_field', 'discount_child_bed')
        ->first();

    $new = new WholesalerFieldMapping();
    $new->wholesaler_id = $wid;
    $new->section_name = 'departure';
    $new->our_field = 'discount_adult';
    $new->their_field = $template->their_field ?? null;
    $new->their_field_path = $template->their_field_path ?? null;
    $new->transform_type = 'formula';
    $new->transform_config = [
        'string_transform' => [
            'type' => 'formula',
            'formulaExpression' => 'abs({period_rate_adult_twn} * {period_promotion_group.discount} / 100)',
        ],
    ];
    $new->save();
    echo "  [ADD]  discount_adult: created with abs() formula\n";
} else {
    $existing->transform_type = 'formula';
    $existing->transform_config = [
        'string_transform' => [
            'type' => 'formula',
            'formulaExpression' => 'abs({period_rate_adult_twn} * {period_promotion_group.discount} / 100)',
        ],
    ];
    $existing->save();
    echo "  [OK]   discount_adult: updated → abs() formula\n";
}

echo "\nDone. Verifying:\n";
$ms = WholesalerFieldMapping::where('wholesaler_id', $wid)
    ->where('section_name', 'departure')
    ->where('our_field', 'like', 'discount%')
    ->get();
foreach ($ms as $m) {
    $expr = $m->transform_config['string_transform']['formulaExpression']
        ?? ($m->their_field_path ?? $m->their_field ?? 'n/a');
    echo "  {$m->our_field} [{$m->transform_type}] = {$expr}\n";
}
