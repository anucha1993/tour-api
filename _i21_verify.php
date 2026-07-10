// Verify adapter now fetches TTN tours correctly
$adapter = App\Services\WholesalerAdapters\AdapterFactory::create(39);
$result = $adapter->fetchTours(null);

echo "success=" . ($result->success ? 'true' : 'false') . "\n";
echo "err=" . ($result->errorMessage ?? '') . "\n";
echo "count=" . count($result->tours ?? []) . "\n";
echo "hasMore=" . ($result->hasMore ? 'true' : 'false') . "\n";

// Check if CKG3U0626 is in the result
$found = null;
foreach ($result->tours ?? [] as $t) {
    if (($t['P_CODE'] ?? '') === 'CKG3U0626') {
        $found = $t;
        break;
    }
}
if ($found) {
    echo "\nCKG3U0626 FOUND in adapter output!\n";
    echo "  P_ID={$found['P_ID']} P_NAME=" . mb_substr($found['P_NAME'],0,80) . "\n";
    echo "  P_PRICE={$found['P_PRICE']}\n";
    $pCount = is_array($found['period'] ?? null) ? count($found['period']) : 0;
    echo "  periods count={$pCount}\n";
    if ($pCount > 0) {
        $p0 = $found['period'][0];
        echo "  first period keys=" . implode(',',array_keys($p0)) . "\n";
        echo "  first period: " . json_encode(array_intersect_key($p0, array_flip(['P_ID','P_DUE_START','P_DUE_END','P_ADULT','P_CHILDPRICE','P_VOLUME','P_AVAILABLE','P_status'])), JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "\nCKG3U0626 NOT found in adapter output.\n";
}
