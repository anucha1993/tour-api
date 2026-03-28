<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$c = App\Models\WholesalerApiConfig::find(6);
if (!$c) { echo "NOT FOUND\n"; exit; }

echo "=== Integration 6 Config ===\n";
echo "ID: {$c->id}\n";
echo "wholesaler_id: {$c->wholesaler_id}\n";
echo "api_base_url: {$c->api_base_url}\n";
echo "auth_type: {$c->auth_type}\n";
echo "sync_mode: {$c->sync_mode}\n";
echo "sync_method: {$c->sync_method}\n";
echo "sync_limit: {$c->sync_limit}\n";
echo "sync_enabled: " . ($c->sync_enabled ? 'true' : 'false') . "\n";
echo "extract_countries_from_name: " . ($c->extract_countries_from_name ? 'true' : 'false') . "\n";
echo "extract_cities_from_name: " . ($c->extract_cities_from_name ? 'true' : 'false') . "\n";
echo "past_period_handling: " . ($c->past_period_handling ?? 'null') . "\n";

echo "\n=== auth_credentials ===\n";
echo json_encode($c->auth_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== aggregation_config ===\n";
echo json_encode($c->aggregation_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Wholesaler info
$w = App\Models\Wholesaler::find($c->wholesaler_id);
echo "\n=== Wholesaler ===\n";
echo "Name: {$w->name} (code={$w->code})\n";

// Field mappings
$maps = App\Models\WholesalerFieldMapping::where('wholesaler_id', $c->wholesaler_id)->where('is_active', true)->get();
echo "\n=== Field Mappings (count: {$maps->count()}) ===\n";
foreach ($maps->groupBy('section_name') as $section => $group) {
    echo "  [{$section}] ({$group->count()} mappings):\n";
    foreach ($group as $m) {
        $extra = '';
        if ($m->transform_type !== 'direct') {
            $extra = ' config=' . json_encode($m->transform_config, JSON_UNESCAPED_UNICODE);
        }
        echo "    {$m->our_field} <= {$m->their_field_path} ({$m->transform_type}){$extra}\n";
    }
}
