<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check meta_title column size
$col = DB::select("SHOW COLUMNS FROM tours WHERE Field = 'meta_title'");
echo "meta_title column type: {$col[0]->Type}\n";

// Check the specific error's raw SQL to see the value
$err = DB::table('sync_error_logs')->where('sync_log_id', 3837)->first();
if ($err) {
    // Extract meta_title value from the SQL in error_message
    $msg = $err->error_message;
    echo "Error: " . substr($msg, 0, 200) . "\n\n";
    
    // Get raw_data to find the meta_title value
    $raw = json_decode($err->raw_data, true);
    if ($raw) {
        $seo = $raw['seo'] ?? [];
        $tour = $raw['tour'] ?? [];
        echo "meta_title from seo section: " . ($seo['meta_title'] ?? 'NOT_SET') . "\n";
        echo "meta_title type: " . gettype($seo['meta_title'] ?? null) . "\n";
        if (isset($seo['meta_title'])) {
            echo "meta_title length: " . mb_strlen($seo['meta_title']) . " chars\n";
        }
        echo "meta_title from tour section: " . ($tour['meta_title'] ?? 'NOT_SET') . "\n";
    }
}
