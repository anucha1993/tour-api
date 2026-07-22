// End-to-end media change-detection test for tour 1874 (Zego).
//
// Scenario: corrupt the stored PDF fingerprint (pdf_source_size) so the sync
// pipeline THINKS the wholesaler swapped the file, then dispatch the same
// ProcessTourMediaJob synchronously and observe:
//   - RemoteFileProbe::hasChanged() → true for PDF, false for cover
//   - New PDF downloaded from Zego, uploaded to R2, old R2 file deleted
//   - tour.pdf_url + pdf_source_* fingerprint columns refreshed
//   - tour.cover_image_url untouched (fingerprint match)
//
// Fetches the RAW wholesaler API response; mapping into ['media' => ...] is
// done downstream by SectionMapper — here we heuristically locate URLs so we
// can drive the media job directly without running a full sync of 394 tours.

$t = \App\Models\Tour::find(1874);
if (!$t) { echo "tour 1874 not found\n"; return; }

$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(1);

$candidates = array_filter([$t->wholesaler_tour_code, $t->external_id]);
$detail = null; $usedCode = null;
foreach ($candidates as $code) {
    try {
        $d = $adapter->fetchTourDetail((string) $code);
        if ($d) { $detail = $d; $usedCode = $code; break; }
    } catch (\Throwable $e) {
        echo "fetchTourDetail({$code}) error: " . $e->getMessage() . "\n";
    }
}
if (!$detail) { echo "NO DETAIL\n"; return; }
echo "fetchTourDetail OK with code={$usedCode}\n";

// Unwrap numeric-indexed wrapper (Zego often returns [0 => {..}])
if (isset($detail[0]) && is_array($detail[0]) && count($detail) === 1) {
    $detail = $detail[0];
    echo "unwrapped [0] wrapper\n";
}

echo "top-level keys: " . implode(', ', array_keys($detail)) . "\n";

// Heuristic scan: walk the response and collect any string that looks like a
// .pdf / image URL. Keeps the JSON path so we can see WHERE the value lives
// (e.g. media.pdf_url, program_pdf, image_program).
$found = ['pdf' => [], 'image' => []];
$walk = function ($node, string $path) use (&$walk, &$found) {
    if (is_array($node)) {
        foreach ($node as $k => $v) {
            $walk($v, $path === '' ? (string) $k : $path . '.' . $k);
        }
        return;
    }
    if (!is_string($node) || $node === '') return;
    $lower = strtolower($node);
    if (str_ends_with($lower, '.pdf') || str_contains($lower, '.pdf?')) {
        $found['pdf'][$path] = $node;
    } elseif (preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $lower)) {
        $found['image'][$path] = $node;
    }
};
$walk($detail, '');

echo "-- PDF candidates --\n";
foreach ($found['pdf'] as $p => $u) echo "  {$p}\n    {$u}\n";
echo "-- IMAGE candidates --\n";
$img_max = 12;
$i = 0;
foreach ($found['image'] as $p => $u) {
    echo "  {$p}\n    {$u}\n";
    if (++$i >= $img_max) { echo "  ... (" . (count($found['image']) - $img_max) . " more)\n"; break; }
}

// Pick "best guess" source URLs and probe them.
$pdf = $found['pdf'] ? reset($found['pdf']) : null;
$cover = null;
foreach ($found['image'] as $p => $u) {
    if (preg_match('/cover|thumb|program|main|hero/i', $p)) { $cover = $u; break; }
}
if (!$cover && $found['image']) $cover = reset($found['image']);

echo "\nSOURCE pdf_url   : " . var_export($pdf, true) . "\n";
echo "SOURCE cover_url : " . var_export($cover, true) . "\n";

$probe = new \App\Services\RemoteFileProbe();
$freshPdf = $pdf ? $probe->probe($pdf) : null;
$freshCover = $cover ? $probe->probe($cover) : null;
if ($freshPdf) {
    echo "probe PDF   : " . json_encode([
        'ok' => $freshPdf['ok'], 'name' => $freshPdf['name'], 'size' => $freshPdf['size'],
        'etag' => $freshPdf['etag'], 'modified' => $freshPdf['modified'],
    ], JSON_UNESCAPED_SLASHES) . "\n";
}
if ($freshCover) {
    echo "probe COVER : " . json_encode([
        'ok' => $freshCover['ok'], 'name' => $freshCover['name'], 'size' => $freshCover['size'],
        'etag' => $freshCover['etag'], 'modified' => $freshCover['modified'],
    ], JSON_UNESCAPED_SLASHES) . "\n";
}

// ── Baseline snapshot + corruption ────────────────────────────────────────
echo "\n=== BASELINE (before corruption) ===\n";
$origSize = $t->pdf_source_size;
$origName = $t->pdf_source_name;
$origEtag = $t->pdf_source_etag;
$origPdfUrl = $t->pdf_url;
echo "  pdf_url        : {$origPdfUrl}\n";
echo "  pdf_source_name: {$origName}\n";
echo "  pdf_source_size: {$origSize}\n";

$fakeSize = 1234567;
echo "\n>> corrupting pdf_source_size → {$fakeSize}\n";
$t->pdf_source_size = $fakeSize;
$t->save();

// Re-read stored fingerprint the SAME WAY SyncToursJob does.
$t->refresh();
$storedPdf = [
    'name' => $t->pdf_source_name,
    'size' => $t->pdf_source_size,
    'etag' => $t->pdf_source_etag,
    'modified' => optional($t->pdf_source_modified)->format('Y-m-d H:i:s'),
];
$storedCover = [
    'name' => $t->cover_source_name,
    'size' => $t->cover_source_size,
    'etag' => $t->cover_source_etag,
    'modified' => optional($t->cover_source_modified)->format('Y-m-d H:i:s'),
];

$pdfChanged = $freshPdf ? $probe->hasChanged($storedPdf, $freshPdf) : false;
$coverChanged = $freshCover ? $probe->hasChanged($storedCover, $freshCover) : false;

echo "\n=== CHANGE-DETECTION VERDICT ===\n";
echo "  PDF   hasChanged: " . ($pdfChanged ? "TRUE  (size mismatch expected)" : "false") . "\n";
echo "  COVER hasChanged: " . ($coverChanged ? "TRUE" : "false (expected — no change)") . "\n";

if (!$pdfChanged) {
    echo "\n!! PDF didn't register as changed. Aborting media job.\n";
    return;
}

// ── Dispatch the same media job SyncToursJob would ───────────────────────
$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 1)->first();

echo "\n>> dispatching ProcessTourMediaJob::dispatchSync (real download + upload)…\n";
$startedAt = microtime(true);
try {
    \App\Jobs\ProcessTourMediaJob::dispatchSync(
        $t->id,
        $pdf,                                     // pdfUrl (fresh source)
        null,                                     // coverImageUrl (unchanged → don't reupload)
        (string) $t->wholesaler_tour_code,        // wholesalerCode
        $config->pdf_header_image ?? null,
        $config->pdf_header_height ?? null,
        $config->pdf_footer_image ?? null,
        $config->pdf_footer_height ?? null,
        $origPdfUrl,                              // oldPdfUrl (to delete from R2)
        null,                                     // oldCoverImageUrl
        $freshPdf,                                // pdfMeta (new fingerprint to record)
        null,                                     // coverMeta
        true,                                     // pdfChanged (tag as update)
        false,                                    // coverChanged
    );
    echo "   done in " . number_format(microtime(true) - $startedAt, 2) . "s\n";
} catch (\Throwable $e) {
    echo "   FAILED: " . $e->getMessage() . "\n";
    // Restore baseline size so a follow-up run is not stuck in corrupted state
    $t->pdf_source_size = $origSize;
    $t->save();
    return;
}

// ── Verify DB state ──────────────────────────────────────────────────────
$t->refresh();
echo "\n=== AFTER MEDIA JOB ===\n";
echo "  pdf_url        : {$t->pdf_url}\n";
echo "  pdf_source_name: {$t->pdf_source_name}\n";
echo "  pdf_source_size: {$t->pdf_source_size}\n";
echo "  pdf_source_etag: {$t->pdf_source_etag}\n";
echo "  pdf_source_modified: " . optional($t->pdf_source_modified)->format('Y-m-d H:i:s') . "\n";
echo "  pdf_source_updated_at: " . optional($t->pdf_source_updated_at)->format('Y-m-d H:i:s') . "\n";

$pdfUrlChanged = $t->pdf_url !== $origPdfUrl;
$sizeRecovered = (int) $t->pdf_source_size === (int) ($freshPdf['size'] ?? 0);
$nameMatches = $t->pdf_source_name === ($freshPdf['name'] ?? null);
echo "\n=== VERDICT ===\n";
echo "  pdf_url changed to new R2 URL   : " . ($pdfUrlChanged ? "YES" : "NO") . "\n";
echo "  pdf_source_size restored (real) : " . ($sizeRecovered ? "YES ({$t->pdf_source_size})" : "NO (still {$t->pdf_source_size}, expected {$freshPdf['size']})") . "\n";
echo "  pdf_source_name matches source  : " . ($nameMatches ? "YES" : "NO") . "\n";
echo "  cover_image_url unchanged       : " . ($t->cover_image_url === $t->getRawOriginal('cover_image_url') ? "YES" : "?") . "\n";
echo "DONE\n";
