<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$s = App\Models\Subscriber::where('email', 'ap.anucha.ap@gmail.com')->first();
if ($s) {
    $s->delete();
    echo "deleted\n";
} else {
    echo "not found\n";
}

// Check main SMTP config
$smtp = App\Models\Setting::get('smtp_config');
echo "Main SMTP: " . json_encode($smtp ? ['host' => $smtp['host'] ?? 'N/A', 'port' => $smtp['port'] ?? 'N/A', 'enabled' => $smtp['enabled'] ?? false] : null) . "\n";

// Check subscriber SMTP config
$subSmtp = App\Models\Setting::get('subscriber_smtp_config');
echo "Sub SMTP: " . json_encode($subSmtp ? ['host' => $subSmtp['host'] ?? 'N/A', 'port' => $subSmtp['port'] ?? 'N/A', 'enabled' => $subSmtp['enabled'] ?? false] : null) . "\n";
