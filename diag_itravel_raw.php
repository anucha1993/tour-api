<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = \App\Models\WholesalerApiConfig::where('headcode_file', 'itravel')->first();
$creds   = $config->auth_credentials ?? [];
$headers = $creds['headers'] ?? [];

$client  = new \GuzzleHttp\Client(['timeout' => 30]);
$resp    = $client->get('https://itravels.center/api/program', ['headers' => $headers]);
$tours   = json_decode($resp->getBody(), true)['data'] ?? [];

echo "Total tours from API: " . count($tours) . "\n\n";

// Check banner_square stats
$hasBanner = 0;
$noBanner  = 0;
$depByCodes = [];
foreach ($tours as $t) {
    if (!empty($t['banner_square'])) $hasBanner++;
    else $noBanner++;
    $dep = $t['departure_by'] ?? null;
    if ($dep && $dep !== 'null') {
        $code = strtoupper(substr(trim((string)$dep), 0, 2));
        $depByCodes[$code] = ($depByCodes[$code] ?? 0) + 1;
    }
}
echo "banner_square SET: $hasBanner / " . count($tours) . "\n";
echo "banner_square NULL: $noBanner / " . count($tours) . "\n";

echo "\ndeparture_by codes and counts:\n";
arsort($depByCodes);
foreach ($depByCodes as $code => $cnt) {
    $transport = \App\Models\Transport::where('code', $code)->orWhere('code1', $code)->first();
    $foundStr  = $transport ? "→ transport_id={$transport->id} ({$transport->name})" : "→ NOT FOUND IN DB";
    echo "  $code : $cnt tours  $foundStr\n";
}

echo "\nFirst 6 tours raw fields:\n";
foreach (array_slice($tours, 0, 6) as $t) {
    echo "  code={$t['code']}";
    echo " | name=" . mb_substr($t['name'] ?? '', 0, 30);
    echo " | day={$t['day']} night={$t['night']}";
    echo " | dep_by=" . ($t['departure_by'] ?? 'null');
    echo " | banner=" . (!empty($t['banner_square']) ? 'SET' : 'NULL');
    echo " | pdf="    . (!empty($t['program_detail_file_pdf']) ? 'SET' : 'NULL');
    echo "\n";
}

// Show periods count for CVZ240
echo "\nPeriods for CVZ240 (tour 1):\n";
$resp2 = $client->get('https://itravels.center/api/program/CVZ240', ['headers' => $headers]);
$periods = json_decode($resp2->getBody(), true)['data'] ?? [];
echo "  Total periods from API: " . count($periods) . "\n";
foreach ($periods as $p) {
    $today = date('Y-m-d');
    $past  = ($p['date_start'] ?? '9999') < $today ? ' [PAST]' : '';
    echo "  id={$p['id']} {$p['date_start']} → {$p['date_end']}  status={$p['status']}  seat={$p['seat']}{$past}\n";
}
