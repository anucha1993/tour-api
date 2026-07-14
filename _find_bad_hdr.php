<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;

$target = 'nexttripholiday5@hotmail.com';

echo "=== Scanning all WholesalerApiConfig auth_credentials for '$target' as header NAME ===\n\n";

foreach (WholesalerApiConfig::all() as $c) {
    $cred = $c->auth_credentials ?? [];
    $issues = [];

    // Recursively scan for the email appearing as an ARRAY KEY (header name) anywhere
    $walk = function ($data, string $path = '') use (&$walk, &$issues, $target) {
        if (!is_array($data)) return;
        foreach ($data as $k => $v) {
            $subPath = $path === '' ? (string)$k : $path.'.'.$k;
            $keyStr = (string) $k;
            // Header names can never contain '@'
            if (str_contains($keyStr, '@')) {
                $issues[] = "  KEY has '@' at [{$subPath}]  key=\"{$keyStr}\"  value=".(is_scalar($v)?json_encode($v):'array');
            }
            if ($keyStr === $target) {
                $issues[] = "  KEY equals target at [{$subPath}]  value=".(is_scalar($v)?json_encode($v):'array');
            }
            if (is_array($v)) $walk($v, $subPath);
        }
    };
    $walk($cred);

    if ($issues) {
        echo "── config id={$c->id}  wholesaler_id={$c->wholesaler_id}  ({$c->wholesaler?->name})\n";
        foreach ($issues as $line) echo $line."\n";
        echo "  full auth_credentials keys top-level: ".implode(',', array_keys($cred))."\n";
        if (isset($cred['headers']) && is_array($cred['headers'])) {
            echo "  headers[] = ".json_encode($cred['headers'], JSON_UNESCAPED_UNICODE)."\n";
        }
        echo "\n";
    }
}

echo "Done.\n";
