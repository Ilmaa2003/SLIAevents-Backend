<!DOCTYPE html>
<html>
<head>
    <title>Payment Bridge Diagnostics</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .test { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; }
        .error { border-left-color: #dc3545; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>🔍 Payment Bridge Diagnostics</h1>
    
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$endpoint = 'https://slia.lk/pay-bridge.php';
$secret_key = '0k9mwKNOTe21gPe1';

echo "<div class='test'>";
echo "<h2>Test 1: Health Check</h2>";
echo "<p>Testing connection to: <strong>$endpoint</strong></p>";

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "<p class='error'>❌ <strong>cURL Error:</strong> $curlError</p>";
} else {
    if ($httpCode === 200) {
        echo "<p class='success'>✅ <strong>HTTP Status:</strong> $httpCode (OK)</p>";
        echo "<p><strong>Response:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<p class='error'>❌ <strong>HTTP Status:</strong> $httpCode</p>";
        echo "<p><strong>Response:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
echo "</div>";

// Test 2: Payment Initiation
echo "<div class='test'>";
echo "<h2>Test 2: Payment Initiation Signature Test</h2>";

$testData = [
    'amount' => 50,
    'email' => 'test@example.com',
    'name' => 'Test User',
    'reference' => 'TEST-' . time(),
    'registration_id' => 999,
    'return_url' => 'https://sliaannualsessions.lk/api/conference/payment-callback'
];

ksort($testData);
$dataString = implode('|', array_values($testData));
$signature = hash_hmac('sha256', $dataString, $secret_key);
$testData['signature'] = $signature;

echo "<p><strong>Test Payload:</strong></p>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "<p class='error'>❌ <strong>cURL Error:</strong> $curlError</p>";
} else {
    echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
    echo "<p><strong>Bridge Response:</strong></p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $result = json_decode($response, true);
    if ($result) {
        echo "<p><strong>Parsed JSON:</strong></p>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($result['success']) && $result['success']) {
            echo "<p class='success'>✅ <strong>Bridge Communication: SUCCESS</strong></p>";
            if (isset($result['payment_url'])) {
                echo "<p class='success'>✅ <strong>Payment URL received!</strong></p>";
            }
        } else {
            echo "<p class='error'>❌ <strong>Bridge returned error:</strong> " . ($result['message'] ?? 'Unknown') . "</p>";
        }
    }
}
echo "</div>";

echo "<div class='test'>";
echo "<h2>Server Information</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>cURL Version:</strong> " . curl_version()['version'] . "</p>";
echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? 'Yes' : 'No') . "</p>";
echo "</div>";
?>

</body>
</html>
