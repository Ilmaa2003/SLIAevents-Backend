<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FellowshipPaymentService
{
    private $clientId;
    private $authToken;
    private $hmacSecret;
    private $endpoint;

    public function __construct()
    {
        // Sampath Bank Paycorp Gateway Credentials
        // Using same credentials as Conference for now, unless specific ones exist
        $this->clientId = env('SAMPATH_CLIENT_ID', '14005990'); // Client ID (LKR)
        $this->authToken = env('SAMPATH_AUTH_TOKEN', 'aa84ea6d-c1ac-4df6-9bfa-2784fe968067'); // Authtoken
        $this->hmacSecret = env('SAMPATH_HMAC_SECRET', '0k9mwKNOTe21gPe1'); // Hmac Secret
        // Changed to point directly to the bridge file on the server root
        $this->endpoint = env('SAMPATH_ENDPOINT', 'https://slia.lk/pay-bridge.php');
    }

    /**
     * Generate HMAC-SHA256 signature for payment data
     */
    protected function generateSignature(array $data): string
    {
        // Sort data by key for consistent signature
        ksort($data);
        
        // Create string from data
        $dataString = implode('|', array_values($data));
        
        // Generate HMAC signature
        return hash_hmac('sha256', $dataString, $this->hmacSecret);
    }

    /**
     * Verify signature from callback
     */
    public function verifySignature(array $data, string $signature): bool
    {
        $expectedSignature = $this->generateSignature($data);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify callback data and signature from slia.lk
     */
    public function verifyCallback(array $data, string $signature): bool
    {
        // The callback from bridge uses the same signature logic
        return $this->verifySignature($data, $signature);
    }

    /**
     * Initiate payment through slia.lk gateway specifically for Fellowship
     */
    public function initiatePayment(array $paymentData): array
    {
        try {
            // Dedicated callback path for Fellowship
            $callbackPath = '/api/fellowship/payment/callback';
            
            // Prepare data for slia.lk
            $data = [
                'name' => $paymentData['full_name'],
                'email' => $paymentData['email'],
                'amount' => number_format((float)$paymentData['amount'], 2, '.', ''),
                'reference' => $paymentData['client_ref'],
                'return_url' => (rtrim(env('FRONTEND_URL'), '/') ?: config('app.url')) . $callbackPath,
                'registration_id' => $paymentData['registration_id'],
                'event_type' => 'fellowship'
            ];

            // Generate signature
            $signature = $this->generateSignature($data);
            $data['signature'] = $signature;

            Log::info('Initiating Fellowship payment (Separate Service)', [
                'reference' => $data['reference'],
                'amount' => $data['amount'],
                'endpoint' => $this->endpoint,
                'return_url' => $data['return_url']
            ]);

            // Send to payment gateway
            Log::info('Sending Fellowship payment request to bridge', [
                'endpoint' => $this->endpoint,
                'reference' => $data['reference'],
                'amount' => $data['amount']
            ]);

            $response = Http::timeout(30)
                ->post($this->endpoint, $data);

            Log::info('Fellowship Bridge response received', [
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 500)
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // CRITICAL FIX: Check if the bridge actually reported success
                if (isset($result['success']) && !$result['success']) {
                    // Log the full bridge response for debugging including new debug field
                    Log::error('Fellowship Payment bridge returned failure', [
                        'bridge_message' => $result['message'] ?? 'Unknown error',
                        'debug_info' => $result['debug'] ?? null,  // NEW: Capture debug field
                        'bank_response' => $result['bank_response'] ?? null,
                        'possible_url_fields' => $result['possible_url_fields'] ?? null,
                        'response_structure' => $result['response_structure'] ?? null,
                        'raw_response_preview' => isset($result['raw_response']) ? substr($result['raw_response'], 0, 500) : null,
                        'full_result' => $result
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Bridge Error: ' . ($result['message'] ?? 'Unknown error'),
                        'details' => $result
                    ];
                }

                return [
                    'success' => true,
                    'payment_url' => $result['payment_url'] ?? $result['redirect_url'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'message' => 'Payment initiated successfully'
                ];
            } else {
                Log::error('Fellowship payment HTTP error', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Payment gateway HTTP error: ' . $response->status() . ' - ' . substr($response->body(), 0, 200)
                ];
            }

        } catch (\Exception $e) {
            Log::error('Fellowship payment initiation exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to slia.lk gateway
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(10)->get($this->endpoint);
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
}
