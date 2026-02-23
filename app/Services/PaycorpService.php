<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaycorpService
{
    protected $endpoint;
    protected $clientId;
    protected $authToken;
    protected $hmacSecret;
    
    public function __construct()
    {
        // Strictly using only these 4 payment keys as requested
        $this->clientId = env('PAYCORP_CLIENT_ID', config('services.paycorp.client_id')); // Client ID (LKR)
        $this->authToken = env('PAYCORP_AUTH_TOKEN', config('services.paycorp.auth_token')); // Authtoken
        $this->hmacSecret = env('PAYCORP_HMAC_SECRET', config('services.paycorp.hmac_secret')); // Hmac Secret
        $this->endpoint = env('PAYCORP_ENDPOINT', config('services.paycorp.endpoint')); // Endpoint
    }
    
    /**
     * Generate HMAC signature for request
     */
    private function generateHmacSignature($payload)
    {
        $message = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $message, $this->hmacSecret);
        return $signature;
    }
    
public function initPayment($data)
{
    try {
        Log::info('=== PAYCORP PAYMENT INIT REQUEST ===', [
            'endpoint' => $this->endpoint,
            'registration_id' => $data['registration_id']
        ]);
        
        // Paycorp expects amount in cents (LKR * 100)
        $amountInCents = (int)($data['amount'] * 100);
        
        $payload = [
            'version' => '1.5',
            'msgId' => uniqid('', true),
            'operation' => 'PAYMENT_INIT',
            'requestDate' => now()->format('Y-m-d\TH:i:s.vO'),
            'validateOnly' => false,
            'requestData' => [
                'clientId' => $this->clientId,
                'clientIdHash' => '',
                'transactionType' => 'PURCHASE',
                'transactionAmount' => [
                    'totalAmount' => $amountInCents,
                    'paymentAmount' => $amountInCents,
                    'serviceFeeAmount' => 0,
                    'currency' => 'LKR'
                ],
                'redirect' => [
                    'returnUrl' => url('/api/conference/payment/callback'),
                    'cancelUrl' => url('/api/conference/payment-failed'),
                    'returnMethod' => 'GET'
                ],
                'clientRef' => 'CONF' . $data['registration_id'],
                'comment' => 'SLIA Conference Registration - ' . $data['full_name'],
                'tokenize' => false,
                'cssLocation1' => '',
                'cssLocation2' => '',
                'useReliability' => true,
                'extraData' => json_encode([
                    'registration_id' => $data['registration_id'],
                    'membership_number' => $data['membership_number'],
                    'full_name' => $data['full_name'],
                    'email' => $data['email']
                ])
            ]
        ];
        
        Log::info('Request Payload:', ['payload' => $payload]);
        
        // Generate HMAC signature
        $hmacSignature = $this->generateHmacSignature($payload);
        
        // Create HTTP client with conditional SSL verification
        $httpClient = Http::timeout(30)
            ->retry(3, 100);
        
        // Disable SSL verification for local/development environments
        if (app()->environment('local', 'development', 'testing')) {
            $httpClient = $httpClient->withoutVerifying();
            Log::info('SSL verification disabled for development environment');
        }
        
        $response = $httpClient->withHeaders([
                'Authorization' => 'Bearer ' . $this->authToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-HMAC-Signature' => $hmacSignature,
            ])
            ->post($this->endpoint, $payload);
        
        Log::info('=== PAYCORP RESPONSE ===', [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body_preview' => substr($response->body(), 0, 500) // Log first 500 chars only
        ]);
        
        if ($response->successful()) {
            $responseData = $response->json();
            
            // Debug log the full response in development
            if (app()->environment('local', 'development')) {
                Log::debug('Full Paycorp response:', ['response' => $responseData]);
            }
            
            if (isset($responseData['responseData']['paymentPageUrl'])) {
                Log::info('Payment init successful', [
                    'payment_page_url' => $responseData['responseData']['paymentPageUrl'],
                    'reqid' => $responseData['responseData']['reqid'] ?? null
                ]);
                
                return [
                    'success' => true,
                    'paymentPageUrl' => $responseData['responseData']['paymentPageUrl'],
                    'reqid' => $responseData['responseData']['reqid'] ?? $responseData['msgId'],
                    'message' => 'Payment initialization successful'
                ];
            } else {
                Log::error('Payment init response missing paymentPageUrl', [
                    'response_keys' => array_keys($responseData),
                    'response_data_keys' => isset($responseData['responseData']) ? array_keys($responseData['responseData']) : 'No responseData'
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Payment gateway returned invalid response: Missing paymentPageUrl',
                    'debug' => app()->environment('local', 'development') ? $responseData : null
                ];
            }
        } else {
            $errorBody = $response->body();
            $statusCode = $response->status();
            
            Log::error('Payment init failed', [
                'status' => $statusCode,
                'body_preview' => substr($errorBody, 0, 500),
                'endpoint' => $this->endpoint
            ]);
            
            // Parse error message based on content type
            $errorMessage = 'Payment gateway error';
            
            // Check if it's an HTML response (wrong endpoint)
            if (str_contains($errorBody, '<html>') || str_contains($errorBody, '<!DOCTYPE')) {
                $errorMessage = 'Received HTML instead of JSON. Please check Paycorp endpoint configuration.';
            } 
            // Check if it's XML response
            elseif (str_contains($errorBody, '<?xml') || str_contains($errorBody, '<Error>')) {
                $errorMessage = 'Received XML error from server. Check API endpoint.';
                
                // Try to extract XML error message
                if (preg_match('/<Message>(.*?)<\/Message>/', $errorBody, $matches)) {
                    $errorMessage .= ' - ' . $matches[1];
                }
            }
            // Try to parse as JSON
            else {
                try {
                    $errorData = json_decode($errorBody, true);
                    if (isset($errorData['error']['message'])) {
                        $errorMessage = $errorData['error']['message'];
                    } elseif (isset($errorData['message'])) {
                        $errorMessage = $errorData['message'];
                    } elseif (isset($errorData['responseData']['message'])) {
                        $errorMessage = $errorData['responseData']['message'];
                    }
                } catch (\Exception $e) {
                    // If not JSON, use status code message
                    $errorMessage = 'HTTP ' . $statusCode . ': ' . substr($errorBody, 0, 200);
                }
            }
            
            // Specific handling for common errors
            if ($statusCode === 401) {
                $errorMessage = 'Authentication failed. Check your Paycorp credentials.';
            } elseif ($statusCode === 403) {
                $errorMessage = 'Access forbidden. Check client ID and permissions.';
            } elseif ($statusCode === 404) {
                $errorMessage = 'API endpoint not found. Check Paycorp endpoint URL.';
            } elseif ($statusCode === 405) {
                $errorMessage = 'Method not allowed. Check if POST method is supported.';
            } elseif ($statusCode === 500) {
                $errorMessage = 'Paycorp server error. Please try again later.';
            }
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'status_code' => $statusCode,
                'debug' => app()->environment('local', 'development') ? [
                    'endpoint' => $this->endpoint,
                    'body_preview' => substr($errorBody, 0, 500)
                ] : null
            ];
        }
        
    } catch (\Exception $e) {
        Log::error('Paycorp Payment Init Exception: ' . $e->getMessage());
        Log::error('Exception Trace: ' . $e->getTraceAsString());
        
        // Check if it's an SSL error
        $errorMessage = $e->getMessage();
        if (str_contains($errorMessage, 'SSL certificate problem') || 
            str_contains($errorMessage, 'cURL error 60')) {
            
            $errorMessage = 'SSL certificate verification failed. ';
            if (app()->environment('local', 'development')) {
                $errorMessage .= 'SSL verification has been disabled for development.';
            } else {
                $errorMessage .= 'Please check your SSL configuration.';
            }
        }
        
        return [
            'success' => false,
            'message' => 'Payment gateway connection error: ' . $errorMessage,
            'debug' => app()->environment('local', 'development') ? [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ] : null
        ];
    }
}
public function completePayment($reqid)
{
    try {
        Log::info('Completing payment for reqid: ' . $reqid);
        
        $payload = [
            'version' => '1.5',
            'msgId' => uniqid('', true),
            'operation' => 'GET_TRANSACTION',
            'requestDate' => now()->format('Y-m-d\TH:i:s.vO'),
            'validateOnly' => false,
            'requestData' => [
                'clientId' => $this->clientId,
                'reqid' => $reqid
            ]
        ];
        
        // Generate HMAC signature
        $hmacSignature = $this->generateHmacSignature($payload);
        
        // Create HTTP client with conditional SSL verification
        $httpClient = Http::timeout(30)
            ->retry(3, 100);
        
        // Disable SSL verification for local/development environments
        if (app()->environment('local', 'development', 'testing')) {
            $httpClient = $httpClient->withoutVerifying();
        }
        
        $response = $httpClient->withHeaders([
                'Authorization' => 'Bearer ' . $this->authToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-HMAC-Signature' => $hmacSignature,
            ])
            ->post($this->endpoint, $payload);
        
        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['responseData']['status'])) {
                $status = $data['responseData']['status'];
                
                if ($status === 'SUCCESS' || $status === 'COMPLETED') {
                    Log::info('Payment completed successfully', [
                        'reqid' => $reqid,
                        'status' => $status,
                        'txnReference' => $data['responseData']['txnReference'] ?? null
                    ]);
                    
                    return [
                        'success' => true,
                        'data' => $data['responseData'],
                        'message' => 'Payment completed successfully'
                    ];
                } else {
                    Log::warning('Payment not successful', [
                        'reqid' => $reqid,
                        'status' => $status,
                        'response' => $data['responseData']
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Payment status: ' . $status,
                        'data' => $data['responseData'] ?? []
                    ];
                }
            } else {
                Log::error('Invalid response from payment gateway', [
                    'reqid' => $reqid,
                    'response' => $data
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Invalid response from payment gateway',
                    'data' => $data['responseData'] ?? []
                ];
            }
        } else {
            Log::error('Payment completion failed', [
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 500)
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to verify payment status. HTTP ' . $response->status()
            ];
        }
        
    } catch (\Exception $e) {
        Log::error('Payment completion error: ' . $e->getMessage());
        
        // Check if it's an SSL error
        $errorMessage = $e->getMessage();
        if (str_contains($errorMessage, 'SSL certificate problem') || 
            str_contains($errorMessage, 'cURL error 60')) {
            
            $errorMessage = 'SSL certificate verification failed during payment completion.';
        }
        
        return [
            'success' => false,
            'message' => 'Payment verification error: ' . $errorMessage
        ];
    }
}
    
    /**
     * Test connection to Paycorp
     */
    public function testConnection()
    {
        try {
            Log::info('Testing Paycorp connection');
            
            $payload = [
                'version' => '1.5',
                'msgId' => uniqid('', true),
                'operation' => 'GET_CLIENT_INFO',
                'requestDate' => now()->format('Y-m-d\TH:i:s.vO'),
                'validateOnly' => true,
                'requestData' => [
                    'clientId' => $this->clientId
                ]
            ];
            
            // Generate HMAC signature
            $hmacSignature = $this->generateHmacSignature($payload);
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->authToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-HMAC-Signature' => $hmacSignature,
                ])
                ->post($this->endpoint, $payload);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['responseData']['clientName'])) {
                    return [
                        'success' => true,
                        'message' => 'Successfully connected to Paycorp API. Client: ' . $data['responseData']['clientName'],
                        'client_info' => $data['responseData']
                    ];
                } else {
                    return [
                        'success' => true,
                        'message' => 'Connected to Paycorp API',
                        'response' => $data
                    ];
                }
            } else {
                $error = $response->body();
                return [
                    'success' => false,
                    'message' => 'Connection failed. HTTP ' . $response->status() . ': ' . substr($error, 0, 200)
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify HMAC signature from callback
     */
    public function verifyHmacSignature($payload, $receivedSignature)
    {
        $expectedSignature = $this->generateHmacSignature($payload);
        return hash_equals($expectedSignature, $receivedSignature);
    }
}