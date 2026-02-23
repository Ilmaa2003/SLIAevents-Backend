<?php
// clear-cache.php
// Place this in public_html/

define('LARAVEL_START', microtime(true));
$basePath = '/home/wi8wkocyhnpw/slia_backend/';

require $basePath.'vendor/autoload.php';
$app = require_once $basePath.'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "<h1>Clearing Application Cache...</h1>";

try {
    echo "Running config:clear... ";
    $kernel->call('config:clear');
    echo "Done.<br>";

    echo "Running cache:clear... ";
    $kernel->call('cache:clear');
    echo "Done.<br>";
    
    echo "Running route:clear... ";
    $kernel->call('route:clear');
    echo "Done.<br>";

    echo "<h3>✅ Cache cleared successfully!</h3>";
    echo "<p>Please try registering again. It should be fast now.</p>";

} catch (Exception $e) {
    echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
}
