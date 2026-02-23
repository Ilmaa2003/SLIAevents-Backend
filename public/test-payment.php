<?php
// Simple test script to verify payment bridge connection
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\SampathPaymentService;

$service = new SampathPaymentService();

echo "Testing Payment Bridge Connection...\n\n";

// Test 1: Connection test
echo "Test 1: Health Check\n";
$result = $service->testConnection();
echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Payment initiation test
echo "Test 2: Payment Init (Test Mode - LKR 50)\n";
$paymentResult = $service->initiatePayment([
    'full_name' => 'Test User',
    'email' => 'test@example.com',
    'amount' => 50,
    'client_ref' => 'TEST-' . time(),
    'registration_id' => 999
]);
echo json_encode($paymentResult, JSON_PRETTY_PRINT) . "\n\n";
