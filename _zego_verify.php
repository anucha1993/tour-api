// Follow-up verification after the corrupt-size test on tour 1874.
//
// (ก) Verify the OLD R2 PDF was actually deleted from storage.
// (ข) Confirm pdf_updated_at (real column name) was tagged — earlier check
//     used the wrong column pdf_source_updated_at → returned NULL silently.
// (ค) Repeat the change-detection flow for COVER by corrupting cover_source_etag.

$t = \App\Models\Tour::find(1874);
if (!$t) { echo "tour 1874 not found\n"; return; }

echo "=== (ข) Correct-column check ===\n";
foreach (['pdf_updated_at','cover_image_updated_at','pdf_source_url','cover_source_url'] as $col) {
    $v = $t->{$col} ?? null;
    if ($v instanceof \DateTimeInterface) $v = $v->format('Y-m-d H:i:s');
    echo "  " . str_pad($col, 24) . ": " . var_export($v, true) . "\n";
}

echo "\n=== (ก) Old R2 PDF deletion check ===\n";
$oldPdfUrl = 'https://files.nexttrip.world/pdfs/zg/2026/04/2257_20260401143050_69cf87a1f4129.pdf';
// deleteOldPdf uses Storage::disk('r2')->exists(path) — mirror that check here.
$oldPath = ltrim((string) parse_url($oldPdfUrl, PHP_URL_PATH), '/');
$existsOnDisk = \Illuminate\Support\Facades\Storage::disk('r2')->exists($oldPath);
echo "  old key    : {$oldPath}\n";
echo "  exists R2  : " . ($existsOnDisk ? 'YES (deletion FAILED)' : 'NO  (deleted ✓)') . "\n";

// Also do a public HEAD probe on the old URL — even if delete succeeded, CDN
// caches may serve it for a bit. RemoteFileProbe returns ok=false on 404.
$probe = new \App\Services\RemoteFileProbe();
$oldProbe = $probe->probe($oldPdfUrl);
echo "  public HEAD: ok=" . ($oldProbe['ok'] ? 'true' : 'false')
    . " size=" . var_export($oldProbe['size'], true) . "\n";

// ── (ค) Cover-change test ────────────────────────────────────────────────
echo "\n=== (ค) COVER change-detection test ===\n";
$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(1);
$detail = $adapter->fetchTourDetail((string) $t->wholesaler_tour_code);
if (isset($detail[0]) && is_array($detail[0]) && count($detail) === 1) $detail = $detail[0];
$coverUrl = $detail['URLImage'] ?? null;
if (!$coverUrl) { echo "no URLImage in Zego response, abort.\n"; return; }
echo "  source cover URL: {$coverUrl}\n";

$freshCover = $probe->probe($coverUrl);
echo "  fresh probe: name={$freshCover['name']} size={$freshCover['size']} etag={$freshCover['etag']}\n";

$origCoverUrl  = $t->cover_image_url;
$origCoverEtag = $t->cover_source_etag;
$origCoverSize = $t->cover_source_size;
echo "  original cover_image_url : {$origCoverUrl}\n";
echo "  original cover_source_etag: {$origCoverEtag}\n";

// Corrupt etag → mismatch triggers hasChanged=true
$fakeEtag = '"corrupted-test-etag-0000"';
echo "  >> corrupting cover_source_etag → {$fakeEtag}\n";
$t->cover_source_etag = $fakeEtag;
$t->save();

$t->refresh();
$storedCover = [
    'name' => $t->cover_source_name,
    'size' => $t->cover_source_size,
    'etag' => $t->cover_source_etag,
    'modified' => optional($t->cover_source_modified)->format('Y-m-d H:i:s'),
];
$coverChanged = $probe->hasChanged($storedCover, $freshCover);
echo "  hasChanged: " . ($coverChanged ? 'TRUE (etag mismatch ✓)' : 'false (UNEXPECTED!)') . "\n";
if (!$coverChanged) {
    echo "  restoring etag and aborting\n";
    $t->cover_source_etag = $origCoverEtag;
    $t->save();
    return;
}

$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 1)->first();
echo "  >> dispatching ProcessTourMediaJob::dispatchSync (cover only)…\n";
$startedAt = microtime(true);
try {
    \App\Jobs\ProcessTourMediaJob::dispatchSync(
        $t->id,
        null,                                     // pdfUrl (unchanged)
        $coverUrl,                                // coverImageUrl
        (string) $t->wholesaler_tour_code,
        $config->pdf_header_image ?? null,
        $config->pdf_header_height ?? null,
        $config->pdf_footer_image ?? null,
        $config->pdf_footer_height ?? null,
        null,                                     // oldPdfUrl
        $origCoverUrl,                            // oldCoverImageUrl (Cloudflare)
        null,                                     // pdfMeta
        $freshCover,                              // coverMeta
        false,                                    // pdfChanged
        true,                                     // coverChanged (tag update)
    );
    echo "  done in " . number_format(microtime(true) - $startedAt, 2) . "s\n";
} catch (\Throwable $e) {
    echo "  FAILED: " . $e->getMessage() . "\n";
    $t->cover_source_etag = $origCoverEtag;
    $t->save();
    return;
}

$t->refresh();
echo "\n=== AFTER COVER JOB ===\n";
echo "  cover_image_url        : {$t->cover_image_url}\n";
echo "  cover_source_name      : {$t->cover_source_name}\n";
echo "  cover_source_size      : {$t->cover_source_size}\n";
echo "  cover_source_etag      : {$t->cover_source_etag}\n";
echo "  cover_image_updated_at : " . optional($t->cover_image_updated_at)->format('Y-m-d H:i:s') . "\n";

$urlChanged  = $t->cover_image_url !== $origCoverUrl;
$etagOk      = $t->cover_source_etag === ($freshCover['etag'] ?? null);
$onCloudflare = str_contains((string) $t->cover_image_url, 'imagedelivery.net');
echo "\n=== VERDICT ===\n";
echo "  cover_image_url refreshed        : " . ($urlChanged ? 'YES' : 'NO (kept old — is delete/upload wired?)') . "\n";
echo "  cover_source_etag restored (real): " . ($etagOk ? 'YES' : "NO ({$t->cover_source_etag})") . "\n";
echo "  new URL on Cloudflare Images     : " . ($onCloudflare ? 'YES' : 'NO') . "\n";
echo "  cover_image_updated_at tagged    : " . ($t->cover_image_updated_at ? 'YES' : 'NO') . "\n";
echo "DONE\n";
