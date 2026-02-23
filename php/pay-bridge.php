<?php
/**
 * Standalone PHP Bridge for Sampath Bank Paycorp Integration
 * Compatible with PHP 5.3+
 * Location: slia.lk
 */

// Simple error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'bridge_error.log');

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// --- Configuration ---
$secret_key = '0k9mwKNOTe21gPe1'; 
$client_id = '14005990';
$auth_token = 'aa84ea6d-c1ac-4df6-9bfa-2784fe968067';
$paycorp_endpoint = 'https://sampath.paycorp.lk/rest/service/proxy';
$main_app_callback = 'https://sliaannualsessions.lk/api/conference/payment-callback';

// Log Helper
function bridge_log($message, $data = null) {
    $entry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $entry .= " - Data: " . (is_string($data) ? $data : json_encode($data));
    }
    file_put_contents('bridge_debug.log', $entry . "\n", FILE_APPEND);
}

// --- Polyfill for hash_equals() ---
if (!function_exists('hash_equals')) {
    function hash_equals($str1, $str2) {
        if (strlen($str1) != strlen($str2)) {
            return false;
        }
        $res = $str1 ^ $str2;
        $ret = 0;
        for ($i = strlen($res) - 1; $i >= 0; $i--) {
            $ret |= ord($res[$i]);
        }
        return !$ret;
    }
}

// --- Health Check ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['mode'])) {
    echo json_encode(array('success' => true, 'message' => 'SLIA Payment Bridge is active.'));
    exit;
}

// --- Callback Handler ---
if (isset($_GET['mode']) && $_GET['mode'] === 'callback') {
    $params = array_merge($_GET, $_POST);
    
    bridge_log("Callback received", $params);

    $transaction_id = '';
    if (isset($params['reqid'])) $transaction_id = $params['reqid'];
    elseif (isset($params['txnReference'])) $transaction_id = $params['txnReference'];
    elseif (isset($params['transaction_id'])) $transaction_id = $params['transaction_id'];

    $amount = 0;
    $status = 'failed'; // Default to failed
    $bank_response = array(); // Store full bank response here

    // IF amount is missing or 0, AND we have a transaction_id (reqid), query the bank!
    // This is the CRITICAL FIX for "amount: 0"
    if ((!isset($params['amount']) || $params['amount'] == 0) && !empty($transaction_id)) {
        bridge_log("Amount missing in callback. Querying bank for details...", $transaction_id);

        // Generate UUID
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $paycorp_req = array(
            'version' => '1.5',
            'msgId' => strtoupper($uuid),
            'operation' => 'PAYMENT_COMPLETE',
            'requestDate' => date('Y-m-d\TH:i:s.000O'),
            'validateOnly' => false,
            'requestData' => array(
                'clientId' => $client_id,
                'comment' => 'Status Inquiry',
                'reqid' => $transaction_id
            )
        );

        $payload_json = json_encode($paycorp_req);
        $hmac_signature = hash_hmac('sha256', $payload_json, $secret_key);

        $ch = curl_init($paycorp_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'AUTHTOKEN: ' . $auth_token,
            'HMAC: ' . $hmac_signature
        ));
        // Strict SSL check disabled for compatibility, enable in prod ifcert valid
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        bridge_log("PAYMENT_COMPLETE Response", $response);

        if (!$curl_error && $response) {
            $bank_response = json_decode($response, true);
            
            if (isset($bank_response['responseData'])) {
                $respData = $bank_response['responseData'];
                
                // Extract amount (in cents, convert to decimal)
                if (isset($respData['transactionAmount']) && isset($respData['transactionAmount']['paymentAmount'])) {
                    $amount = $respData['transactionAmount']['paymentAmount'] / 100;
                }

                // Extract status
                if (isset($respData['responseCode']) && $respData['responseCode'] === '00') {
                    $status = 'completed';
                } elseif (isset($respData['txnStatus']) && $respData['txnStatus'] === 'APPROVED') {
                    $status = 'completed';
                } elseif (isset($respData['status']) && strtolower($respData['status']) === 'approved') {
                    $status = 'completed';
                }
                
                // Merge this back into params for consistency
                $params['responseCode'] = isset($respData['responseCode']) ? $respData['responseCode'] : '';
                $params['responseMessage'] = isset($respData['responseText']) ? $respData['responseText'] : '';
                $params['bankReference'] = isset($respData['authCode']) ? $respData['authCode'] : '';
                $params['paymentMethod'] = isset($respData['cardType']) ? $respData['cardType'] : '';
            }
        }
    } else {
        // Fallback to normal parsing if amount exists
        // Determine Status
        if (isset($params['status']) && ($params['status'] === 'success' || $params['status'] === 'completed')) {
            $status = 'completed';
        } elseif (isset($params['responseCode']) && $params['responseCode'] === '00') {
            $status = 'completed';
        } elseif (isset($params['txnStatus']) && $params['txnStatus'] === 'APPROVED') {
            $status = 'completed';
        }

        // Determine Amount
        if (isset($params['amount'])) $amount = $params['amount'];
        elseif (isset($params['paymentAmount'])) $amount = $params['paymentAmount'];
        elseif (isset($params['txnAmount'])) $amount = $params['txnAmount'];
        elseif (isset($params['transactionAmount'])) $amount = $params['transactionAmount'];
        elseif (isset($params['totalAmount'])) $amount = $params['totalAmount'];
    }

    // Determine Reference
    $reference = '';
    if (isset($params['clientRef'])) $reference = $params['clientRef'];
    elseif (isset($params['reference'])) $reference = $params['reference'];

    $callback_data = array(
        'transaction_id' => $transaction_id,
        'reference' => $reference,
        'status' => $status,
        'amount' => $amount,
        'bank_reference' => isset($params['bankReference']) ? $params['bankReference'] : (isset($params['authCode']) ? $params['authCode'] : ''),
        'payment_method' => isset($params['paymentMethod']) ? $params['paymentMethod'] : '',
        'response_code' => isset($params['responseCode']) ? $params['responseCode'] : '',
        'response_message' => isset($params['responseMessage']) ? $params['responseMessage'] : (isset($params['message']) ? $params['message'] : ''),
        'timestamp' => date('Y-m-d H:i:s'),
        // SAFETY NET: Pass everything back so we can see what's wrong in the main app logs
        '_debug_raw' => json_encode(array_merge($params, array('bank_query_response' => $bank_response)))
    );
    
    bridge_log("Final processed callback data", $callback_data);
    
    ksort($callback_data);
    $data_string = implode('|', array_values($callback_data));
    $signature = hash_hmac('sha256', $data_string, $secret_key);
    $callback_data['signature'] = $signature;
    
    $query = http_build_query($callback_data);
    $redirect_url = $main_app_callback . '?' . $query;
    
    bridge_log("Redirecting to app", $redirect_url);
    
    header('Location: ' . $redirect_url);
    exit;
}

// --- Payment Initiation ---
$raw_input = file_get_contents('php://input');
bridge_log("Payment Initiation Request", $raw_input);

$data = json_decode($raw_input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Invalid JSON payload'));
    exit;
}

// Verify signature
$received_signature = isset($data['signature']) ? $data['signature'] : '';
unset($data['signature']);

ksort($data);
$data_string = implode('|', array_values($data));
$expected_signature = hash_hmac('sha256', $data_string, $secret_key);

if (!hash_equals($expected_signature, $received_signature)) {
    bridge_log("Signature mismatch on initiation", array('expected' => $expected_signature, 'received' => $received_signature));
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Signature mismatch'));
    exit;
}

// Build Paycorp payload
$amount_in_cents = round($data['amount'] * 100);

// Generate UUID for msgId
$uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$paycorp_payload = array(
    'version' => '1.5',
    'msgId' => strtoupper($uuid),
    'operation' => 'PAYMENT_INIT',
    'requestDate' => date('Y-m-d\TH:i:s.000O'),
    'validateOnly' => false,
    'requestData' => array(
        'clientId' => $client_id,
        'clientIdHash' => '',
        'transactionType' => 'PURCHASE',
        'transactionAmount' => array(
            'totalAmount' => $amount_in_cents,
            'paymentAmount' => $amount_in_cents,
            'serviceFeeAmount' => 0,
            'currency' => 'LKR'
        ),
        'redirect' => array(
            'returnUrl' => 'https://slia.lk/pay-bridge.php?mode=callback',
            'cancelUrl' => 'https://slia.lk/pay-bridge.php?mode=callback',
            'returnMethod' => 'GET'
        ),
        'clientRef' => $data['reference'],
        'comment' => 'SLIA Conference - ' . substr($data['name'], 0, 40),
        'tokenize' => false,
        'cssLocation1' => '',
        'cssLocation2' => '',
        'useReliability' => true,
        'extraData' => array(
            'email' => $data['email'],
            'name' => substr($data['name'], 0, 50)
        )
    )
);

$payload_json = json_encode($paycorp_payload);
$hmac_signature = hash_hmac('sha256', $payload_json, $secret_key);

bridge_log("Sending to Paycorp", $paycorp_payload);

// Make cURL request
$ch = curl_init($paycorp_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Accept: application/json',
    'AUTHTOKEN: ' . $auth_token,
    'HMAC: ' . $hmac_signature
));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

bridge_log("Paycorp Response", array('code' => $http_code, 'body' => $response, 'error' => $curl_error));

// Handle errors
if ($curl_error) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Connection error: ' . $curl_error
    ));
    exit;
}

if ($http_code !== 200) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Bank error (HTTP ' . $http_code . ')',
        'details' => substr($response, 0, 500)
    ));
    exit;
}

$result = json_decode($response, true);

if (!$result) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid bank response'
    ));
    exit;
}

// Check for errors
if (isset($result['error'])) {
    $error_msg = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
    echo json_encode(array(
        'success' => false,
        'message' => 'Bank error: ' . $error_msg
    ));
    exit;
}

// Extract payment URL
$payment_url = null;
$transaction_id = null;

if (isset($result['responseData'])) {
    if (isset($result['responseData']['paymentUrl'])) {
        $payment_url = $result['responseData']['paymentUrl'];
    } elseif (isset($result['responseData']['paymentPageUrl'])) {
        $payment_url = $result['responseData']['paymentPageUrl'];
    } elseif (isset($result['responseData']['payment_url'])) {
        $payment_url = $result['responseData']['payment_url'];
    } elseif (isset($result['responseData']['redirectUrl'])) {
        $payment_url = $result['responseData']['redirectUrl'];
    }
    
    if (isset($result['responseData']['txnReference'])) {
        $transaction_id = $result['responseData']['txnReference'];
    } elseif (isset($result['responseData']['transactionId'])) {
        $transaction_id = $result['responseData']['transactionId'];
    } elseif (isset($result['responseData']['reqid'])) {
        $transaction_id = $result['responseData']['reqid'];
    }
}

if (!$payment_url && isset($result['paymentUrl'])) {
    $payment_url = $result['paymentUrl'];
}

if (!$payment_url && isset($result['paymentPageUrl'])) {
    $payment_url = $result['paymentPageUrl'];
}

if (!$payment_url && isset($result['payment_url'])) {
    $payment_url = $result['payment_url'];
}

if ($payment_url) {
    echo json_encode(array(
        'success' => true,
        'payment_url' => $payment_url,
        'transaction_id' => $transaction_id,
        'message' => 'Payment URL generated'
    ));
} else {
    // Return detailed debug info
    $bank_msg = 'No message';
    if (isset($result['message'])) {
        $bank_msg = $result['message'];
    } elseif (isset($result['responseMessage'])) {
        $bank_msg = $result['responseMessage'];
    }
    
    $debug_info = array(
        'http_code' => $http_code,
        'response_keys' => array_keys($result),
        'bank_message' => $bank_msg,
        'full_bank_response' => $result,
        'has_error' => isset($result['error']),
        'error_details' => isset($result['error']) ? $result['error'] : null,
        'has_responseData' => isset($result['responseData']),
        'responseData_keys' => isset($result['responseData']) ? array_keys($result['responseData']) : array()
    );
    
    echo json_encode(array(
        'success' => false,
        'message' => 'Bank did not return payment URL. Check credentials.',
        'debug' => $debug_info
    ));
}
