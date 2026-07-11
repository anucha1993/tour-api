<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "R2_URL=" . env('R2_URL') . "\n\n";

$header = "https://imagedelivery.net/OGiukopN6pbQwdTofcZnpg/integration-header-39-1783684650/public";
$footer = "https://imagedelivery.net/OGiukopN6pbQwdTofcZnpg/integration-footer-39-1783684657/public";

foreach (['header'=>$header,'footer'=>$footer] as $k=>$url) {
    try {
        $resp = Illuminate\Support\Facades\Http::timeout(30)->get($url);
        $ct = $resp->header('Content-Type');
        echo "$k: status={$resp->status()} content-type={$ct} bytes=" . strlen($resp->body()) . "\n";
        // Test if getimagesize can read it
        $tmp = sys_get_temp_dir()."/test_$k";
        file_put_contents($tmp, $resp->body());
        $info = @getimagesize($tmp);
        echo "  getimagesize: " . ($info ? "{$info[0]}x{$info[1]} type={$info[2]} mime={$info['mime']}" : "FAILED (not a readable image / webp?)") . "\n";
        @unlink($tmp);
    } catch (\Exception $e) {
        echo "$k ERROR: ".$e->getMessage()."\n";
    }
}
