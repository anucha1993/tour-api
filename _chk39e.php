<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$cnt = DB::table('jobs')->where('queue','media')->count();
echo "media jobs now: $cnt\n\n";

$job = DB::table('jobs')->where('queue','media')->orderBy('id')->first();
if ($job) {
    $body = json_decode($job->payload, true);
    $cmd = $body['data']['command'] ?? '';
    // Unserialize to read protected props
    $obj = @unserialize($cmd);
    if ($obj) {
        $r = new ReflectionObject($obj);
        foreach (['tourId','pdfUrl','coverImageUrl','pdfHeaderImage','pdfFooterImage','wholesalerCode'] as $p) {
            if ($r->hasProperty($p)) {
                $pr = $r->getProperty($p); $pr->setAccessible(true);
                $v = $pr->getValue($obj);
                echo "  $p = " . (is_null($v)?'NULL':substr((string)$v,0,90)) . "\n";
            }
        }
    } else {
        echo "could not unserialize; raw cmd:\n".substr($cmd,0,600)."\n";
    }
}
