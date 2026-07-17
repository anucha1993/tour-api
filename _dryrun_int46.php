<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;

$config = WholesalerApiConfig::findOrFail(46);
$headers = $config->auth_credentials['headers'] ?? [];
$url = 'https://api-formosa.ht1freshdigital.com/wp-json/bs-api/v1/tour-dates?tour_id=22246';

// Fetch periods
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(fn($k,$v)=>$k.': '.$v, array_keys($headers), array_values($headers)));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$body = curl_exec($ch);
$data = json_decode($body, true);
$periods = $data['data'] ?? [];
echo "Fetched ".count($periods)." periods\n\n";

// Load a dummy job just to reuse applyTransform via reflection
$mappings = WholesalerFieldMapping::where('wholesaler_id', 54)
    ->where('section_name', 'departure')
    ->where('is_active', 1)
    ->get();

// Recreate applyTransform logic inline (simple version)
$applyTransform = function($value, $type, $cfg) {
    $cfg = is_array($cfg) ? $cfg : [];
    if (!$type || $type === 'direct') return $value;
    switch ($type) {
        case 'custom':
            if (($cfg['operation'] ?? null) === 'date_range') {
                $part = ($cfg['part'] ?? 'start') === 'end' ? 'end' : 'start';
                $norm = str_replace(["\u{2013}", "\u{2014}"], '-', trim((string)$value));
                $segs = preg_split('/\s+(?:-|to|ถึง)\s+/u', $norm) ?: [];
                $segs = array_values(array_filter(array_map('trim', $segs), fn($s)=>$s!==''));
                if (empty($segs)) return null;
                $sel = $part==='end' ? end($segs) : $segs[0];
                $ts = strtotime($sel);
                return $ts ? date('Y-m-d', $ts) : null;
            }
            return $value;
        case 'value_map':
            $map = $cfg['map'] ?? [];
            if (!is_array($map)) return $value;
            $key = ($value === '' || $value === null) ? '' : $value;
            return array_key_exists($key, $map) ? $map[$key] : ($cfg['default'] ?? $value);
        default:
            return $value;
    }
};

$extractByPath = function($data, $path) {
    if (!$path) return null;
    $ref = $data;
    foreach (explode('.', $path) as $seg) {
        if (is_array($ref) && array_key_exists($seg, $ref)) $ref = $ref[$seg];
        else return null;
    }
    return $ref;
};

// Transform first 3 periods
echo "=== Dry-run: Transform first 3 periods ===\n";
foreach (array_slice($periods, 0, 3) as $i => $raw) {
    echo "\n--- Period #".($i+1)." (raw period='{$raw['period']}') ---\n";
    $out = [];
    foreach ($mappings as $m) {
        $path = $m->their_field_path ?: $m->their_field;
        if (!$path) continue;
        $val = $extractByPath($raw, $path);
        $val = $applyTransform($val, $m->transform_type, $m->transform_config);
        if ($val === null && $m->default_value !== null) $val = $m->default_value;
        $out[$m->our_field] = $val;
    }
    foreach ($out as $k => $v) {
        printf("    %-20s = %s\n", $k, is_scalar($v) ? $v : json_encode($v));
    }
}

// Also validate active-future logic
echo "\n=== Would be counted as active_future_periods? ===\n";
$today = date('Y-m-d');
$activeCount = 0;
foreach ($periods as $raw) {
    $path = 'period';
    $val = $extractByPath($raw, $path);
    $start = $applyTransform($val, 'custom', ['operation'=>'date_range','part'=>'start']);
    $status = $applyTransform($extractByPath($raw, 'post_status'), 'value_map', ['map'=>['publish'=>'open','draft'=>'closed'],'default'=>'open']);
    $isActive = $start && $start >= $today && in_array($status, ['open','waitlist','sold_out']);
    if ($isActive) $activeCount++;
}
echo "  total periods: ".count($periods)."\n";
echo "  active_future_periods: $activeCount\n";
echo "  today: $today\n";
echo $activeCount > 0 ? "\n✅ SAFE: tour would NOT be auto-deleted\n" : "\n⚠️  DANGER: tour would still be auto-deleted!\n";
