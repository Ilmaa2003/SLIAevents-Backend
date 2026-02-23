<?php
// debug-queue.php
// Place this file in public_html/ and visit it in browser

define('LARAVEL_START', microtime(true));
$basePath = '/home/wi8wkocyhnpw/slia_backend/';

if (!file_exists($basePath . 'vendor/autoload.php')) {
    die("Error: Could not find backend at $basePath");
}

require $basePath.'vendor/autoload.php';
$app = require_once $basePath.'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "<h1>Queue Debugger</h1>";

try {
    $jobs = Illuminate\Support\Facades\DB::table('jobs')->get();
    $failedJobs = Illuminate\Support\Facades\DB::table('failed_jobs')->orderBy('id', 'desc')->get();

    echo "<h2>Pending Jobs (Count: " . $jobs->count() . ")</h2>";
    if ($jobs->count() > 0) {
        echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Queue</th><th>Attempts</th><th>Available At</th></tr>";
        foreach ($jobs as $job) {
            echo "<tr><td>{$job->id}</td><td>{$job->queue}</td><td>{$job->attempts}</td><td>" . date('Y-m-d H:i:s', $job->available_at) . "</td></tr>";
        }
        echo "</table>";
        echo "<p>⚠️ <strong>If jobs are stuck here:</strong> The queue processor is NOT running. Run <code>process-queue.php</code> manually or check Cron Job.</p>";
    } else {
        echo "<p>No pending jobs. (Good, unless they vanished without sending)</p>";
    }

    echo "<h2>Failed Jobs (Count: " . $failedJobs->count() . ")</h2>";
    if ($failedJobs->count() > 0) {
        echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Date</th><th>Exception</th></tr>";
        foreach ($failedJobs as $job) {
            echo "<tr>";
            echo "<td>{$job->id}</td>";
            echo "<td>{$job->failed_at}</td>";
            echo "<td><div style='max-height: 200px; overflow: auto; white-space: pre-wrap; color: red;'>" . htmlspecialchars(substr($job->exception, 0, 2000)) . "...</div></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No failed jobs. (Good!)</p>";
    }

    // Check .env config for queue
    echo "<h2>Current Configuration</h2>";
    echo "QUEUE_CONNECTION: <strong>" . config('queue.default') . "</strong><br>";
    echo "Mail Mailer: <strong>" . config('mail.default') . "</strong><br>";
    echo "Mail Host: <strong>" . config('mail.mailers.smtp.host') . "</strong><br>";
    echo "Mail Port: <strong>" . config('mail.mailers.smtp.port') . "</strong><br>";
    echo "Mail Encryption: <strong>" . config('mail.mailers.smtp.encryption') . "</strong><br>";

    // Attempt to manually process one job
    if ($jobs->count() > 0) {
        echo "<h2>Attempting to Process 1 Job Now...</h2>";
        $kernel->call('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--timeout' => 30
        ]);
        echo "<pre>" . htmlspecialchars(Artisan::output()) . "</pre>";
        echo "<p>Check above if it worked.</p>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
