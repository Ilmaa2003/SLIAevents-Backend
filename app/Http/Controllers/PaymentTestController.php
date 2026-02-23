<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\SampathPaymentService;

class PaymentTestController extends Controller
{
    /**
     * Test the payment bridge connection
     */
    public function testBridge()
    {
        try {
            $service = new SampathPaymentService();
            
            // Test data
            $testData = [
                'full_name' => 'Test User',
                'email' => 'test@example.com',
                'amount' => 50,
                'client_ref' => 'TEST-' . time(),
                'registration_id' => 999
            ];
            
            Log::info('Testing payment bridge with data:', $testData);
            
            $result = $service->initiatePayment($testData);
            
            Log::info('Payment bridge test result:', $result);
            
            return response()->json([
                'success' => true,
                'message' => 'Bridge test completed',
                'result' => $result,
                'test_data' => $testData
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment bridge test failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    /**
     * Test direct HTTP connection to bridge
     */
    public function testConnection()
    {
        try {
            $endpoint = env('SAMPATH_ENDPOINT', 'https://slia.lk/pay-bridge.php');
            
            // Test GET request (health check)
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            return response()->json([
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'curl_error' => $curlError ?: null,
                'response' => $response,
                'connection_ok' => ($httpCode === 200 && !$curlError)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
