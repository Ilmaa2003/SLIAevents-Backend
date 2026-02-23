<?php
/**
 * Test Script for pay-bridge.php
 * 
 * This script simulates a payment request to test the bridge
 * and see the actual bank response
 */

// Test data
$testData = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'amount' => 2000, // LKR 2000
    'reference' => 'TEST-CONF-' . time(),
    'registration_id' => 999
];

// Encode as JSON
$jsonData = json_encode($testData);

echo "=== Testing pay-bridge.php ===\n\n";
echo "Sending test payment request...\n";
echo "Amount: LKR {$testData['amount']}\n";
echo "Reference: {$testData['reference']}\n\n";

// Call the bridge
$ch = curl_init('https://slia.lk/pay-bridge.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($curlError) {
    echo "CURL Error: $curlError\n";
}

echo "\n=== RESPONSE ===\n";
echo $response . "\n";

// Pretty print JSON response
$responseData = json_decode($response, true);
if ($responseData) {
    echo "\n=== PARSED RESPONSE ===\n";
    print_r($responseData);
    
    // Check for payment URL
    if (isset($responseData['payment_url'])) {
        echo "\n✅ SUCCESS! Payment URL found:\n";
        echo $responseData['payment_url'] . "\n";
    } else {
        echo "\n❌ NO PAYMENT URL in response\n";
        
        // Show what fields ARE present
        echo "\nAvailable fields:\n";
        echo implode(", ", array_keys($responseData)) . "\n";
        
        // If there's raw_response or bank_response, show it
        if (isset($responseData['raw_response'])) {
            echo "\n=== RAW BANK RESPONSE ===\n";
            echo $responseData['raw_response'] . "\n";
        }
        
        if (isset($responseData['bank_response'])) {
            echo "\n=== BANK RESPONSE ===\n";
            print_r($responseData['bank_response']);
        }
    }
} else {
    echo "Failed to parse JSON response\n";
}

echo "\n=== END TEST ===\n";
