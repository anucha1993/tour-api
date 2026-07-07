<?php

/**
 * One-off data repair: fix corrupted array-cast columns on the `tours` table.
 *
 * Two corruption types are handled:
 *   1. Double-encoded array   raw = '"[\"a\",\"b\"]"'  → decode twice → ["a","b"]
 *   2. Scalar wrapped by cast  raw = '"foo bar"'        → decode once  → "foo bar" → ["foo bar"]
 *
 * A healthy value raw starts with '[' and is left untouched.
 *
 * Run:  php fix_double_encoded_arrays.php          (dry-run, reports only)
 *       php fix_double_encoded_arrays.php --apply  (writes changes)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);

$fields = [
    'highlights', 'shopping_highlights', 'food_highlights', 'special_highlights',
    'hashtags', 'keywords', 'themes', 'suitable_for', 'departure_airports',
];

/**
 * Normalize a raw DB JSON value into a clean array (or null if it should be left alone).
 * Returns [$shouldFix, $normalizedArray].
 */
function normalizeRaw(?string $raw): array
{
    if ($raw === null || $raw === '' || $raw === 'null') {
        return [false, null];
    }

    // Healthy JSON array already → leave it.
    if ($raw[0] === '[') {
        return [false, null];
    }

    // Corrupted: starts with a quote (JSON-encoded string).
    $d1 = json_decode($raw, true);

    if (is_array($d1)) {
        // Unusual, but already an array after one decode → normalize values.
        return [true, array_values($d1)];
    }

    if (is_string($d1)) {
        $d2 = json_decode($d1, true);
        if (is_array($d2)) {
            // Double-encoded array.
            return [true, array_values($d2)];
        }
        // Wrapped scalar string → single-element array.
        $s = trim($d1);
        return [true, $s === '' ? [] : [$s]];
    }

    return [false, null];
}

echo $apply ? "=== APPLY MODE (writing changes) ===\n" : "=== DRY RUN (no changes) — add --apply to write ===\n";

$grandTotal = 0;

foreach ($fields as $field) {
    $fixed = 0;
    $samples = [];

    DB::table('tours')
        ->whereNotNull($field)
        ->where($field, '!=', 'null')
        ->where($field, '!=', '[]')
        ->select('id', $field)
        ->orderBy('id')
        ->chunk(1000, function ($rows) use ($field, $apply, &$fixed, &$samples) {
            foreach ($rows as $row) {
                $raw = $row->{$field};
                [$shouldFix, $normalized] = normalizeRaw($raw);
                if (!$shouldFix) {
                    continue;
                }

                if (count($samples) < 2) {
                    $samples[] = "  id={$row->id}: " . mb_substr($raw, 0, 60)
                        . ' → ' . json_encode($normalized, JSON_UNESCAPED_UNICODE);
                }

                if ($apply) {
                    DB::table('tours')->where('id', $row->id)->update([
                        $field => json_encode($normalized, JSON_UNESCAPED_UNICODE),
                    ]);
                }
                $fixed++;
            }
        });

    if ($fixed > 0) {
        echo sprintf("%-22s %s %d rows\n", $field . ':', $apply ? 'fixed' : 'would fix', $fixed);
        foreach ($samples as $s) {
            echo $s . "\n";
        }
    } else {
        echo sprintf("%-22s clean\n", $field . ':');
    }
    $grandTotal += $fixed;
}

echo "\n" . ($apply ? "Total fixed: " : "Total to fix: ") . $grandTotal . "\n";
