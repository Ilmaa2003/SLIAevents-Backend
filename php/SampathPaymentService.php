<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SampathPaymentService
{
    private $clientId;
    private $authToken;
    private $hmacSecret;
    private $endpoint;

    public function __construct()
    {
        // Sampath Bank Paycorp Gateway Credentials
        $this->clientId = env('SAMPATH_CLIENT_ID', '14005990'); // Client ID (LKR)
        $this->authToken = env('SAMPATH_AUTH_TOKEN', '64509e4d-f919-4028-84db-0325afee6889'); // Authtoken
        $this->hmacSecret = env('SAMPATH_HMAC_SECRET', 'x0AmSSU0SbSdRAI0'); // Hmac Secret
        $this->endpoint = env('SAMPATH_ENDPOINT', 'https://slia.lk/api/sampath/initiate'); // Endpoint (Relay)
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
     * Initiate payment through slia.lk gateway
     */
    public function initiatePayment(array $paymentData): array
    {
        try {
            // Prepare data for slia.lk
            $data = [
                'name' => $paymentData['full_name'],
                'email' => $paymentData['email'],
                'amount' => $paymentData['amount'],
                'reference' => $paymentData['client_ref'], // e.g., CONF12345
                'return_url' => config('app.url') . '/api/conference/payment-callback',
                'registration_id' => $paymentData['registration_id']
            ];

            // Generate signature
            $signature = $this->generateSignature($data);
            $data['signature'] = $signature;

            Log::info('Initiating Sampath payment', [
                'reference' => $data['reference'],
                'amount' => $data['amount'],
                'endpoint' => $this->endpoint
            ]);

            // Send to payment gateway
            $response = Http::timeout(30)
                ->post($this->endpoint, $data);

            if ($response->successful()) {
                $result = $response->json();
                
                return [
                    'success' => true,
                    'payment_url' => $result['payment_url'] ?? $result['redirect_url'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'message' => 'Payment initiated successfully'
                ];
            } else {
                Log::error('Sampath payment initiation failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Payment gateway error: ' . $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error('Sampath payment initiation exception', [
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
     * Test connection to payment gateway
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
