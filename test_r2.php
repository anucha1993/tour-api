<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing R2 Storage connection...\n";

// Show config
$config = config('filesystems.disks.r2');
echo "R2 Config:\n";
echo "- driver: " . ($config['driver'] ?? 'NOT SET') . "\n";
echo "- bucket: " . ($config['bucket'] ?? 'NOT SET') . "\n";
echo "- endpoint: " . ($config['endpoint'] ?? 'NOT SET') . "\n";
echo "- url: " . ($config['url'] ?? 'NOT SET') . "\n";
echo "- key: " . substr($config['key'] ?? '', 0, 5) . "... (length: " . strlen($config['key'] ?? '') . ")\n";
echo "- secret: " . substr($config['secret'] ?? '', 0, 5) . "... (length: " . strlen($config['secret'] ?? '') . ")\n";
echo "\n";

try {
    // Use AWS SDK directly to test
    echo "Testing direct AWS SDK connection...\n";
    
    $s3Client = new Aws\S3\S3Client([
        'version' => 'latest',
        'region' => $config['region'] ?? 'auto',
        'endpoint' => $config['endpoint'],
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key' => $config['key'],
            'secret' => $config['secret'],
        ],
    ]);
    
    echo "S3 Client created successfully\n";
    
    // Try to put object
    $result = $s3Client->putObject([
        'Bucket' => $config['bucket'],
        'Key' => 'test.txt',
        'Body' => 'Hello R2 Test ' . date('Y-m-d H:i:s'),
        'ContentType' => 'text/plain',
    ]);
    
    echo "PutObject result:\n";
    print_r($result->toArray());
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
