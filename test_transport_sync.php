<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\SyncPeriodsJob;
use Illuminate\Support\Facades\DB;

echo "=== Testing Transport Sync ===\n\n";

// Clear existing tour_transports for tour 272
DB::table('tour_transports')->where('tour_id', 272)->delete();
echo "Cleared tour_transports for tour 272\n\n";

// Run sync for tour 272
echo "Running SyncPeriodsJob for tour 272...\n";
$job = new SyncPeriodsJob(272, 6);
$job->handle();

echo "\n=== Checking Results ===\n\n";

// Check tour_transports table
$records = DB::table('tour_transports')->where('tour_id', 272)->get();
echo "tour_transports for tour 272: " . count($records) . " records\n";
foreach ($records as $r) {
    echo "  - transport_id: {$r->transport_id}, is_primary: {$r->is_primary}\n";
}

// Check tours.transport_id
$tour = DB::table('tours')->where('id', 272)->first();
echo "\ntours.transport_id: " . ($tour->transport_id ?? 'null') . "\n";

// Get transport name
if ($tour->transport_id) {
    $transport = DB::table('transports')->where('id', $tour->transport_id)->first();
    echo "Transport: {$transport->code} - {$transport->name}\n";
}
