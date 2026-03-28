<?php
// Verify data format issues for BEST API

echo "=== Date Format Test ===\n";
// dateGo = "04/04/2026" - MM/DD/YYYY
$dateGo = "04/04/2026";
echo "dateGo raw: {$dateGo}\n";
echo "strtotime: " . date('Y-m-d', strtotime($dateGo)) . "\n"; // PHP handles M/D/Y

$dateBack = "04/11/2026";
echo "dateBack raw: {$dateBack}\n";  
echo "strtotime: " . date('Y-m-d', strtotime($dateBack)) . "\n";

echo "\n=== GroupSize Parsing ===\n";
$groupSize = "26+1";
echo "groupSize raw: {$groupSize}\n";
// Need to extract first number before +
echo "intval: " . intval($groupSize) . "\n"; // Gets 26

$groupSize2 = "10+1";
echo "groupSize2 raw: {$groupSize2}\n";
echo "intval: " . intval($groupSize2) . "\n"; // Gets 10

echo "\n=== Available (avbl) field ===\n";
$avbl_values = ["ปิดกรุ๊ป", "เต็ม", 12, 24, 26];
foreach ($avbl_values as $v) {
    echo "avbl = " . json_encode($v, JSON_UNESCAPED_UNICODE);
    if (is_numeric($v)) {
        echo " → numeric: {$v}";
    } else {
        echo " → string (0 available)";
    }
    echo "\n";
}
// avbl can be:
// - numeric: actual available seats
// - "ปิดกรุ๊ป" (closed group) = 0
// - "เต็ม" (full) = 0
// Need value_map or special handling

echo "\n=== Capacity from groupSize ===\n";
// groupSize = "26+1" means 26 passengers + 1 tour leader
// capacity should be 26
// BUT intval("26+1") = 26 which is correct
echo "intval('26+1') = " . intval('26+1') . "\n";
echo "intval('15+1') = " . intval('15+1') . "\n";

echo "\n=== External ID ===\n";
// Using numeric id (529) as external_id
// But wholesaler_tour_code uses code ("BT-EUR32_EK")
echo "id=529, code=BT-EUR32_EK\n";
echo "external_id should ideally be a unique stable identifier\n";
echo "Both id and code can work, but code is more readable\n";
