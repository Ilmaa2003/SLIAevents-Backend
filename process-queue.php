<?php
// process-queue.php
// Place this file in public_html/

define('LARAVEL_START', microtime(true));

// UPDATE THIS PATH TO YOUR ACTUAL BACKEND PATH
$basePath = '/home/wi8wkocyhnpw/slia_backend/';

if (!file_exists($basePath . 'vendor/autoload.php')) {
    die("Error: Could not find backend at $basePath");
}

require $basePath.'vendor/autoload.php';
$app = require_once $basePath.'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Starting queue processing...\n";

try {
    // Process queue
    $kernel->call('queue:work', [
        '--stop-when-empty' => true,
        '--tries' => 3,
        '--timeout' => 60
    ]);
    
    echo "Queue processed successfully.";
} catch (Exception $e) {
    echo "Error processing queue: " . $e->getMessage();
}
