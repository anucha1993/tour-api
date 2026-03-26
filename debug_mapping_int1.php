<?php
/**
 * Debug: Show integration 1 mapping configuration
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// --- Integration 1 config ---
$config = \App\Models\WholesalerApiConfig::find(1);
if (!$config) { echo "Integration 1 not found\n"; exit(1); }

echo "=== Integration 1 Config ===\n";
echo "wholesaler_id: {$config->wholesaler_id}\n";
echo "integration_type: " . ($config->integration_type ?? 'config') . "\n";
echo "api_base_url: {$config->api_base_url}\n";
echo "sync_mode: " . ($config->sync_mode ?? 'single') . "\n";
echo "api_format: " . ($config->api_format ?? '?') . "\n";

$wholesaler = $config->wholesaler;
echo "wholesaler: " . ($wholesaler->name ?? $wholesaler->code ?? '?') . " (id={$config->wholesaler_id})\n\n";

// --- Field Mappings for this wholesaler ---
$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $config->wholesaler_id)
    ->where('is_active', true)
    ->orderBy('section_name')
    ->orderBy('sort_order')
    ->get();

echo "=== Field Mappings (wholesaler_id={$config->wholesaler_id}) ===\n";
echo "Total active mappings: " . $mappings->count() . "\n\n";

$grouped = $mappings->groupBy('section_name');
foreach ($grouped as $section => $fields) {
    echo "── {$section} (" . $fields->count() . " fields) ──\n";
    foreach ($fields as $m) {
        $source = $m->their_field ?: $m->their_field_path ?: '-';
        $transform = $m->transform_type ?? 'direct';
        $transformConfig = $m->transform_config ? json_encode($m->transform_config, JSON_UNESCAPED_UNICODE) : '';
        $default = $m->default_value !== null ? " [default: {$m->default_value}]" : '';
        $required = $m->is_required_override ? ' *' : '';
        
        echo sprintf("  %-30s ← %-40s  (%s)%s%s\n", 
            $m->our_field, $source, $transform, $default, $required);
        
        if ($transformConfig && $transform !== 'direct') {
            echo "       transform_config: {$transformConfig}\n";
        }
    }
    echo "\n";
}

// --- Section Definitions (what fields exist in the system) ---
echo "=== Section Definitions (all available fields) ===\n";
$sections = \App\Models\SectionDefinition::orderBy('section_name')->orderBy('sort_order')->get();
$sectionGrouped = $sections->groupBy('section_name');
foreach ($sectionGrouped as $section => $fields) {
    echo "── {$section} (" . $fields->count() . " fields) ──\n";
    foreach ($fields as $f) {
        $type = $f->data_type ?? '?';
        $required = $f->is_required ? ' *REQUIRED' : '';
        $lookup = $f->lookup_table ? " [lookup: {$f->lookup_table}]" : '';
        echo sprintf("  %-30s  %-10s%s%s\n", $f->field_name, $type, $required, $lookup);
    }
    echo "\n";
}
