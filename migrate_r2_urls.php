<?php

/**
 * One-off data migration: rewrite tour PDF URLs from the temporary R2 dev domain
 * (pub-5cbbaf...r2.dev) to the custom domain (files.nexttrip.world).
 * The object path after the domain is identical, so only the host changes.
 *
 * Run:  php migrate_r2_urls.php          (dry-run, reports counts)
 *       php migrate_r2_urls.php --apply  (writes changes)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);

$OLD = 'https://pub-5cbbaf6280f04ca487a930d56cd23307.r2.dev';
$NEW = 'https://files.nexttrip.world';

// column => table map of URL fields that may contain the old R2 domain.
$targets = [
    'tours' => ['pdf_url', 'docx_url', 'cover_image_url', 'og_image_url'],
];

echo $apply ? "=== APPLY MODE (writing changes) ===\n" : "=== DRY RUN — add --apply to write ===\n";
echo "OLD: $OLD\nNEW: $NEW\n\n";

$grand = 0;

foreach ($targets as $table => $columns) {
    foreach ($columns as $col) {
        // Skip columns that don't exist on this table.
        try {
            $affected = DB::table($table)->where($col, 'like', $OLD . '%')->count();
        } catch (\Throwable $e) {
            echo sprintf("%-28s (no column, skipped)\n", "$table.$col:");
            continue;
        }

        if ($affected === 0) {
            echo sprintf("%-28s clean\n", "$table.$col:");
            continue;
        }

        if ($apply) {
            // MySQL REPLACE() swaps only the host prefix; the path is preserved.
            DB::table($table)
                ->where($col, 'like', $OLD . '%')
                ->update([$col => DB::raw("REPLACE($col, '$OLD', '$NEW')")]);
        }

        echo sprintf("%-28s %s %d rows\n", "$table.$col:", $apply ? 'updated' : 'would update', $affected);
        $grand += $affected;
    }
}

echo "\n" . ($apply ? 'Total updated: ' : 'Total to update: ') . $grand . "\n";

if ($apply) {
    // Show a sample to confirm.
    $sample = DB::table('tours')->whereNotNull('pdf_url')->where('pdf_url', 'like', $NEW . '%')->value('pdf_url');
    echo "Sample migrated pdf_url: " . ($sample ?: '(none)') . "\n";
}
