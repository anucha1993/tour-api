<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'Content-Type' => 'application/json',
    'itravels-secret' => env('ITRAVEL_API_KEY'),
])->get('https://itravels.center/api/program/JXJ231');

if ($response->successful()) {
    $data = $response->json();
    echo "Status: {$data['result']}\n";
    echo "Periods: " . count($data['data']) . "\n\n";
    
    $today = date('Y-m-d');
    echo "Today: {$today}\n\n";
    
    foreach ($data['data'] as $p) {
        $isPast = $p['date_start'] < $today ? 'PAST' : 'FUTURE';
        echo "{$p['id']} | {$p['date_start']} - {$p['date_end']} | status: {$p['status']} | seat: {$p['seat']} | avail: {$p['available_seat']} | {$isPast}\n";
    }
} else {
    echo "Error: {$response->status()}\n";
    echo $response->body() . "\n";
}


