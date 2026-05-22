<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = \App\Models\Booking::find(8);
echo "booking_ref: ".($b->provider_booking_ref ?? 'NULL')."\n\n";
echo "=== provider_payload.submit ===\n";
echo json_encode($b->provider_payload['submit'] ?? null, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
