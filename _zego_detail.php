// Read-only: fetch the real Zego source media URLs for the test tour.
$t = \App\Models\Tour::find(1874);
$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(1);

$candidates = [$t->wholesaler_tour_code, $t->external_id];
$detail = null;
foreach ($candidates as $code) {
    if (!$code) continue;
    try {
        $d = $adapter->fetchTourDetail((string) $code);
        if ($d) { $detail = $d; echo "fetchTourDetail OK with code={$code}\n"; break; }
        echo "fetchTourDetail returned null for code={$code}\n";
    } catch (\Throwable $e) {
        echo "fetchTourDetail({$code}) error: " . $e->getMessage() . "\n";
    }
}

if (!$detail) { echo "NO DETAIL\n"; return; }

echo "top-level keys: " . implode(', ', array_keys($detail)) . "\n";
$media = $detail['media'] ?? [];
echo "media keys: " . implode(', ', array_keys($media)) . "\n";
$pdf = $media['pdf_url'] ?? ($detail['pdf_url'] ?? null);
$cover = $media['cover_image_url'] ?? ($detail['cover_image_url'] ?? null);
echo "SOURCE pdf_url   : " . var_export($pdf, true) . "\n";
echo "SOURCE cover_url : " . var_export($cover, true) . "\n";

$probe = new \App\Services\RemoteFileProbe();
if ($pdf) {
    $p = $probe->probe($pdf);
    echo "probe PDF   : " . json_encode(['ok'=>$p['ok'],'name'=>$p['name'],'size'=>$p['size'],'etag'=>$p['etag'],'modified'=>$p['modified']], JSON_UNESCAPED_SLASHES) . "\n";
}
if ($cover) {
    $c = $probe->probe($cover);
    echo "probe COVER : " . json_encode(['ok'=>$c['ok'],'name'=>$c['name'],'size'=>$c['size'],'etag'=>$c['etag'],'modified'=>$c['modified']], JSON_UNESCAPED_SLASHES) . "\n";
}
echo "DONE\n";
