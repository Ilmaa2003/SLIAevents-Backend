<?php

namespace App\Http\Controllers;

use App\Models\ConferenceRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class ConferenceRegistrationController extends Controller
{
    // Sampath Bank Payment Gateway Config
    private $sampathConfig = [
        'merchant_id' => env('SAMPATH_MERCHANT_ID', 'TESTMERCHANT'),
        'merchant_key' => env('SAMPATH_MERCHANT_KEY', ''),
        'currency' => 'LKR',
        'return_url' => '/conference/payment/callback', // Your callback URL
        'cancel_url' => '/conference/payment/cancel',
        'notify_url' => '/conference/payment/notify',
        'test_mode' => env('SAMPATH_TEST_MODE', true),
        'api_endpoint' => env('SAMPATH_TEST_MODE', true) 
            ? 'https://test.sampath.lk/ipgtest/v1/payment/initiate' 
            : 'https://sampath.lk/ipg/v1/payment/initiate',
    ];

    /**
     * Verify membership number for conference
     */
    public function verifyMember($membership_number)
    {
        try {
            $membership_number = trim(strtoupper($membership_number));
            
            if (empty($membership_number)) {
                return response()->json([
                    'status' => 'invalid_member',
                    'message' => 'Membership number is required.'
                ], 400);
            }

            // Check if already registered for conference
            $existing = ConferenceRegistration::where('membership_number', $membership_number)->first();
            if ($existing) {
                return response()->json([
                    'status' => 'already_registered',
                    'message' => 'This membership has already been registered for the conference.',
                    'registered_at' => $existing->created_at->format('Y-m-d H:i:s'),
                    'existing_email' => $existing->email
                ]);
            }

            // Verify in member database
            $member = DB::table('member_details')
                ->where('membership_no', $membership_number)
                ->first();

            if (!$member) {
                return response()->json([
                    'status' => 'invalid_member',
                    'message' => 'Membership number not found.'
                ], 404);
            }

            return response()->json([
                'status' => 'valid',
                'member' => [
                    'full_name' => $member->full_name ?? '',
                    'email' => $member->personal_email ?? ($member->official_email ?? ''),
                    'mobile' => $member->personal_mobilenumber ?? ($member->official_mobilenumber ?? ''),
                    'nic' => $member->nic_number ?? ''
                ],
                'discount' => true // 50% discount for conference
            ]);

        } catch (\Exception $e) {
            Log::error('Conference Verification error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify membership. Please try again.'
            ], 500);
        }
    }

    /**
     * Calculate conference fees
     */
    private function calculateConferenceFees($category, $includeLunch = false)
    {
        $baseFees = [
            'slia_member' => 5000,    // After 50% discount
            'general_public' => 10000,
            'international' => 15000, // USD 50 equivalent
        ];

        $lunchFee = $includeLunch ? 10000 : 0;
        $baseFee = $baseFees[$category] ?? 10000;
        
        // Note: According to your document, tax is added later
        return [
            'base_fee' => $baseFee,
            'lunch_fee' => $lunchFee,
            'total_amount' => $baseFee + $lunchFee,
            'currency' => 'LKR',
            'category' => $category,
            'include_lunch' => $includeLunch,
        ];
    }

    /**
     * Initialize Sampath payment
     */
    public function initiatePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership_number' => 'nullable|string',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'nic_passport' => 'nullable|string|max:50',
            'category' => 'required|in:slia_member,general_public,international',
            'include_lunch' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $membership_number = isset($data['membership_number']) ? trim(strtoupper($data['membership_number'])) : null;

        // Validate member for SLIA category
        if ($data['category'] === 'slia_member' && empty($membership_number)) {
            return response()->json([
                'success' => false,
                'message' => 'Membership number is required for SLIA members.'
            ], 422);
        }

        // Check if already registered
        if ($membership_number) {
            $existing = ConferenceRegistration::where('membership_number', $membership_number)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already registered for conference.',
                    'registered_at' => $existing->created_at->format('Y-m-d H:i:s')
                ], 409);
            }
        }

        DB::beginTransaction();
        
        try {
            // Calculate fees
            $fees = $this->calculateConferenceFees(
                $data['category'], 
                $data['include_lunch'] ?? false
            );

            // Generate unique registration number
            $registrationNumber = 'CONF' . date('Ymd') . Str::random(6);

            // Create registration with pending payment
            $registration = ConferenceRegistration::create([
                'membership_number' => $membership_number,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'category' => $data['category'],
                'member_verified' => ($data['category'] === 'slia_member' && $membership_number) ? true : false,
                'nic_passport' => $data['nic_passport'] ?? null,
                'include_lunch' => $data['include_lunch'] ?? false,
                'total_amount' => $fees['total_amount'],
                'payment_status' => 'pending',
                'concession_eligible' => ($data['category'] === 'slia_member') ? true : false,
            ]);

            Log::info('Conference registration created with ID: ' . $registration->id);

            // Prepare Sampath payment data
            $paymentData = [
                'merchantId' => $this->sampathConfig['merchant_id'],
                'merchantKey' => $this->sampathConfig['merchant_key'],
                'orderId' => $registrationNumber,
                'amount' => number_format($fees['total_amount'], 2, '.', ''),
                'currency' => $this->sampathConfig['currency'],
                'customerFirstName' => $data['full_name'],
                'customerLastName' => '',
                'customerEmail' => $data['email'],
                'customerPhone' => $data['phone'],
                'customerAddress' => '',
                'customerCity' => '',
                'customerCountry' => 'LK',
                'description' => 'SLIA National Conference 2025 Registration',
                'returnUrl' => url($this->sampathConfig['return_url']),
                'cancelUrl' => url($this->sampathConfig['cancel_url']),
                'notifyUrl' => url($this->sampathConfig['notify_url']),
                'timestamp' => time(),
            ];

            // Generate signature (MD5 hash)
            $signatureString = implode('', [
                $paymentData['merchantId'],
                $paymentData['orderId'],
                $paymentData['amount'],
                $paymentData['currency'],
                $paymentData['customerFirstName'],
                $paymentData['customerEmail'],
                $paymentData['timestamp'],
                $this->sampathConfig['merchant_key']
            ]);
            
            $paymentData['signature'] = md5($signatureString);

            Log::info('Initiating Sampath payment for registration: ' . $registrationNumber);

            // In test mode, return mock payment URL
            if ($this->sampathConfig['test_mode']) {
                $paymentUrl = 'https://test.sampath.lk/ipgtest/v1/payment/process?' . http_build_query($paymentData);
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment initiated successfully.',
                    'registration_id' => $registration->id,
                    'registration_number' => $registrationNumber,
                    'payment_url' => $paymentUrl,
                    'test_mode' => true,
                    'fees' => $fees
                ]);
            }

            // Live mode - call Sampath API
            $response = Http::post($this->sampathConfig['api_endpoint'], $paymentData);

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['status'] === 'SUCCESS') {
                    // Update registration with payment reference
                    $registration->update([
                        'payment_ref_no' => $result['transactionId'] ?? $registrationNumber
                    ]);
                    
                    DB::commit();
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Payment initiated successfully.',
                        'registration_id' => $registration->id,
                        'registration_number' => $registrationNumber,
                        'payment_url' => $result['paymentUrl'],
                        'transaction_id' => $result['transactionId'],
                        'fees' => $fees
                    ]);
                } else {
                    throw new \Exception('Payment gateway error: ' . ($result['message'] ?? 'Unknown error'));
                }
            } else {
                throw new \Exception('Payment gateway connection failed.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Conference Payment Initiation Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed. Please try again.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Sampath payment callback (customer return)
     */
    public function paymentCallback(Request $request)
    {
        Log::info('Conference Payment Callback Received:', $request->all());

        $status = $request->input('status');
        $orderId = $request->input('orderId');
        $transactionId = $request->input('transactionId');
        $amount = $request->input('amount');

        try {
            // Verify signature
            $receivedSignature = $request->input('signature');
            $expectedSignature = md5(implode('', [
                $this->sampathConfig['merchant_id'],
                $orderId,
                $amount,
                $this->sampathConfig['currency'],
                $status,
                $transactionId,
                $this->sampathConfig['merchant_key']
            ]));

            if ($receivedSignature !== $expectedSignature) {
                Log::error('Invalid payment signature', [
                    'received' => $receivedSignature,
                    'expected' => $expectedSignature
                ]);
                return redirect('/conference/registration/failed?reason=invalid_signature');
            }

            // Find registration by order ID
            $registration = ConferenceRegistration::where('membership_number', 'LIKE', "%{$orderId}%")
                ->orWhere('payment_ref_no', $orderId)
                ->first();

            if (!$registration) {
                Log::error('Registration not found for order: ' . $orderId);
                return redirect('/conference/registration/failed?reason=not_found');
            }

            if ($status === 'SUCCESS') {
                // Update registration as paid
                $registration->update([
                    'payment_status' => 'completed',
                    'payment_ref_no' => $transactionId,
                    'payment_date' => now(),
                ]);

                // Generate QR code and send email
                $this->sendConfirmationEmail($registration);

                return redirect('/conference/registration/success?id=' . $registration->id);
            } else {
                // Payment failed
                $registration->update(['payment_status' => 'failed']);
                return redirect('/conference/registration/failed?id=' . $registration->id);
            }

        } catch (\Exception $e) {
            Log::error('Payment Callback Error: ' . $e->getMessage());
            return redirect('/conference/registration/failed?reason=error');
        }
    }

    /**
     * Sampath payment notification (server-to-server)
     */
    public function paymentNotify(Request $request)
    {
        Log::info('Conference Payment Notification:', $request->all());

        // Similar to callback but for server-side processing
        // You can implement email notifications to admin here
        
        return response()->json(['status' => 'ok']);
    }

    /**
     * Send confirmation email with QR code
     */
    private function sendConfirmationEmail($registration)
    {
        try {
            // Generate QR code
            $qrContent = json_encode([
                'id' => $registration->id,
                'membership' => $registration->membership_number,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'category' => $registration->category,
                'timestamp' => now()->timestamp,
                'event' => 'SLIA National Conference 2025',
                'venue' => 'Bandaranaike Memorial International Conference Hall (BMICH)',
                'date' => '2025-02-20'
            ]);
            
            $qrSvg = QrCode::format('svg')
                ->size(400)
                ->margin(2)
                ->color(30, 64, 175) // Blue color for conference
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->generate($qrContent);
            
            $qrCode = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

            // Generate PDF
            $pdf = Pdf::loadView('pdf.conference-pass', [
                'registration' => $registration,
                'qr_code' => $qrCode,
                'event_date' => '20 February 2025',
                'event_venue' => 'BMICH',
                'concession_eligible' => $registration->concession_eligible
            ]);

            $pdfContent = $pdf->output();

            // Send email
            Mail::send('emails.conference-confirmation', [
                'registration' => $registration,
                'event_date' => '20 February 2025',
                'event_venue' => 'BMICH',
                'total_amount' => number_format($registration->total_amount, 2),
                'concession_note' => $registration->concession_eligible 
                    ? 'Note: You are eligible for LKR 3,000 membership fee concession upon attendance.' 
                    : ''
            ], function ($message) use ($registration, $pdfContent) {
                $message->to($registration->email)
                        ->subject('SLIA National Conference 2025 - Registration Confirmation')
                        ->attachData($pdfContent, 
                            'SLIA-Conference-Pass-' . ($registration->membership_number ?? $registration->id) . '.pdf',
                            ['mime' => 'application/pdf']
                        );
            });

            // Update registration
            $registration->update([
                'email_sent' => true,
                'qr_data' => $qrContent
            ]);

            Log::info('Conference confirmation email sent to: ' . $registration->email);

        } catch (\Exception $e) {
            Log::error('Conference email sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus($registrationId)
    {
        try {
            $registration = ConferenceRegistration::findOrFail($registrationId);
            
            return response()->json([
                'success' => true,
                'payment_status' => $registration->payment_status,
                'payment_ref_no' => $registration->payment_ref_no,
                'registration_complete' => $registration->payment_status === 'completed',
                'email_sent' => $registration->email_sent,
                'registration' => $registration
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration not found.'
            ], 404);
        }
    }

    /**
     * Resend conference email
     */
    public function resendEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.'
            ], 422);
        }

        try {
            $registration = ConferenceRegistration::where('membership_number', $request->membership_number)
                ->where('email', $request->email)
                ->where('payment_status', 'completed')
                ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paid registration not found.'
                ], 404);
            }

            $this->sendConfirmationEmail($registration);

            return response()->json([
                'success' => true,
                'message' => 'Conference confirmation has been resent to your email.'
            ]);

        } catch (\Exception $e) {
            Log::error('Conference resend email failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to resend email.'
            ], 500);
        }
    }

    /**
     * Get registration by ID for success page
     */
    public function getRegistration($id)
    {
        try {
            $registration = ConferenceRegistration::findOrFail($id);
            
            // Generate QR code for display
            $qrContent = json_decode($registration->qr_data, true) ?? [
                'id' => $registration->id,
                'membership' => $registration->membership_number,
                'name' => $registration->full_name,
                'category' => $registration->category,
                'event' => 'SLIA National Conference 2025'
            ];
            
            $qrSvg = QrCode::format('svg')
                ->size(300)
                ->margin(2)
                ->color(30, 64, 175)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->generate(json_encode($qrContent));
            
            $qrCode = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

            return response()->json([
                'success' => true,
                'registration' => $registration,
                'qr_code' => $qrCode,
                'category_label' => $registration->getCategoryLabelAttribute(),
                'event_date' => '20 February 2025',
                'event_venue' => 'BMICH'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration not found.'
            ], 404);
        }
    }


    // Add these methods to the ConferenceRegistrationController class

/**
 * Get all conference registrations for admin
 */
public function getAllRegistrations(Request $request)
{
    try {
        $perPage = $request->get('per_page', 20);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $paymentStatus = $request->get('payment_status');
        $category = $request->get('category');
        $attended = $request->get('attended');
        $search = $request->get('search');

        $query = ConferenceRegistration::query();

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($attended !== null) {
            $query->where('attended', filter_var($attended, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('membership_number', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('payment_ref_no', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortBy, $sortOrder);
        
        if ($request->get('paginate', true)) {
            $registrations = $query->paginate($perPage);
            $data = $registrations->items();
            $meta = [
                'total' => $registrations->total(),
                'per_page' => $registrations->perPage(),
                'current_page' => $registrations->currentPage(),
                'last_page' => $registrations->lastPage(),
            ];
        } else {
            $data = $query->get();
            $meta = [
                'total' => count($data),
                'per_page' => null,
                'current_page' => 1,
                'last_page' => 1,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta
        ], 200);

    } catch (\Exception $e) {
        Log::error('Get conference registrations failed: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Unable to fetch registrations'], 500);
    }
}

/**
 * Export conference registrations to CSV
 */
public function exportRegistrations(Request $request)
{
    try {
        $query = ConferenceRegistration::query();
        
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        $registrations = $query->get();

        $filename = 'conference-registrations-' . date('Y-m-d-H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($registrations) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'Registration ID',
                'Membership Number',
                'Full Name',
                'Email',
                'Phone',
                'Category',
                'Payment Status',
                'Payment Reference',
                'Total Amount',
                'Include Lunch',
                'Concession Eligible',
                'Attended',
                'Meal Received',
                'Registration Date',
                'Payment Date'
            ]);

            foreach ($registrations as $registration) {
                fputcsv($file, [
                    $registration->id,
                    $registration->membership_number,
                    $registration->full_name,
                    $registration->email,
                    $registration->phone,
                    $registration->category,
                    $registration->payment_status,
                    $registration->payment_ref_no,
                    $registration->total_amount,
                    $registration->include_lunch ? 'Yes' : 'No',
                    $registration->concession_eligible ? 'Yes' : 'No',
                    $registration->attended ? 'Yes' : 'No',
                    $registration->meal_received ? 'Yes' : 'No',
                    $registration->created_at->format('Y-m-d H:i:s'),
                    $registration->payment_date ? $registration->payment_date->format('Y-m-d H:i:s') : ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);

    } catch (\Exception $e) {
        Log::error('Conference export failed: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Unable to export data'], 500);
    }
}

/**
 * Update a conference registration
 */
public function updateRegistration(Request $request, $id)
{
    try {
        $registration = ConferenceRegistration::find($id);
        
        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Conference registration not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'category' => 'sometimes|in:slia_member,general_public,international',
            'include_lunch' => 'sometimes|boolean',
            'attended' => 'sometimes|boolean',
            'meal_received' => 'sometimes|boolean',
            'concession_applied' => 'sometimes|boolean',
            'payment_status' => 'sometimes|in:pending,completed,failed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // If marking meal received but not attended, don't allow
        if (isset($data['meal_received']) && $data['meal_received'] && !$registration->attended && !isset($data['attended'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark meal received without attendance.'
            ], 400);
        }

        $registration->update($data);

        Log::info('Conference registration updated', [
            'id' => $registration->id,
            'membership_number' => $registration->membership_number,
            'updates' => $data
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conference registration updated successfully.',
            'data' => $registration
        ]);

    } catch (\Exception $e) {
        Log::error('Conference update failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Unable to update conference registration.'
        ], 500);
    }
}

/**
 * Delete a conference registration
 */
public function deleteRegistration($id)
{
    try {
        $registration = ConferenceRegistration::find($id);
        
        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Conference registration not found.'
            ], 404);
        }

        Log::info('Deleting conference registration', [
            'id' => $registration->id,
            'membership_number' => $registration->membership_number,
            'name' => $registration->full_name
        ]);

        $registration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conference registration deleted successfully.'
        ]);

    } catch (\Exception $e) {
        Log::error('Delete conference registration failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Unable to delete conference registration.'
        ], 500);
    }
}

/**
 * Generate QR code for a conference registration
 */
public function generateQrCode($id)
{
    try {
        $registration = ConferenceRegistration::find($id);
        
        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Conference registration not found.'
            ], 404);
        }

        $qrContent = json_encode([
            'id' => $registration->id,
            'membership' => $registration->membership_number,
            'name' => $registration->full_name,
            'email' => $registration->email,
            'category' => $registration->category,
            'payment_status' => $registration->payment_status,
            'timestamp' => now()->timestamp,
            'event' => 'SLIA National Conference 2025',
            'attended' => $registration->attended,
            'meal_received' => $registration->meal_received
        ]);
        
        $qrSvg = QrCode::format('svg')
            ->size(400)
            ->margin(2)
            ->color(30, 64, 175)
            ->backgroundColor(255, 255, 255)
            ->errorCorrection('H')
            ->generate($qrContent);
        
        $qrCode = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        return response()->json([
            'success' => true,
            'qr_code' => $qrCode,
            'data' => [
                'membership_number' => $registration->membership_number,
                'full_name' => $registration->full_name,
                'email' => $registration->email,
                'category' => $registration->category,
                'payment_status' => $registration->payment_status,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Conference QR code generation failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Unable to generate QR code.'
        ], 500);
    }
}

/**
 * Mark attendance for conference
 */
public function markAttendance(Request $request, $id)
{
    try {
        $registration = ConferenceRegistration::find($id);
        
        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Conference registration not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'attended' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Check if payment is completed before marking attendance
        if ($data['attended'] && $registration->payment_status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark attendance for unpaid registration.'
            ], 400);
        }

        $registration->update([
            'attended' => $data['attended'],
            'meal_received' => $data['attended'] ? $registration->meal_received : false
        ]);

        Log::info('Conference attendance marked', [
            'id' => $registration->id,
            'membership_number' => $registration->membership_number,
            'attended' => $data['attended']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance marked successfully.',
            'data' => $registration
        ]);

    } catch (\Exception $e) {
        Log::error('Conference attendance marking failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Unable to mark attendance.'
        ], 500);
    }
}

/**
 * Mark meal as received for conference
 */
public function markMealReceived(Request $request, $id)
{
    try {
        $registration = ConferenceRegistration::find($id);
        
        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'Conference registration not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'meal_received' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Check if attended before marking meal received
        if ($data['meal_received'] && !$registration->attended) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mark meal received without attendance.'
            ], 400);
        }

        // Check if include_lunch is true
        if ($data['meal_received'] && !$registration->include_lunch) {
            return response()->json([
                'success' => false,
                'message' => 'Registration does not include lunch.'
            ], 400);
        }

        $registration->update(['meal_received' => $data['meal_received']]);

        Log::info('Conference meal marked', [
            'id' => $registration->id,
            'membership_number' => $registration->membership_number,
            'meal_received' => $data['meal_received']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal status updated successfully.',
            'data' => $registration
        ]);

    } catch (\Exception $e) {
        Log::error('Conference meal marking failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Unable to update meal status.'
        ], 500);
    }
}

/**
 * Get conference statistics
 */
public function getStats()
{
    try {
        $total = ConferenceRegistration::count();
        $attended = ConferenceRegistration::where('attended', true)->count();
        $notAttended = ConferenceRegistration::where('attended', false)->count();
        $today = ConferenceRegistration::whereDate('created_at', today())->count();
        $lastWeek = ConferenceRegistration::whereDate('created_at', '>=', now()->subDays(7))->count();
        $mealReceived = ConferenceRegistration::where('meal_received', true)->count();
        
        // Payment stats
        $paymentCompleted = ConferenceRegistration::where('payment_status', 'completed')->count();
        $paymentPending = ConferenceRegistration::where('payment_status', 'pending')->count();
        $paymentFailed = ConferenceRegistration::where('payment_status', 'failed')->count();
        
        // Category stats
        $sliaMembers = ConferenceRegistration::where('category', 'slia_member')->count();
        $generalPublic = ConferenceRegistration::where('category', 'general_public')->count();
        $international = ConferenceRegistration::where('category', 'international')->count();
        
        // Financial stats
        $totalAmount = ConferenceRegistration::where('payment_status', 'completed')->sum('total_amount');
        
        $attendanceRate = $total > 0 ? round(($attended / $total) * 100, 2) : 0;
        $paymentSuccessRate = $total > 0 ? round(($paymentCompleted / $total) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'total_registrations' => $total,
                'registered_today' => $today,
                'registered_last_7_days' => $lastWeek,
                'attended' => $attended,
                'not_attended' => $notAttended,
                'attendance_rate' => $attendanceRate . '%',
                'meal_received' => $mealReceived,
                'payment_completed' => $paymentCompleted,
                'payment_pending' => $paymentPending,
                'payment_failed' => $paymentFailed,
                'payment_success_rate' => $paymentSuccessRate . '%',
                'total_amount' => $totalAmount,
                'category_breakdown' => [
                    'slia_member' => $sliaMembers,
                    'general_public' => $generalPublic,
                    'international' => $international
                ],
                'last_registration' => ConferenceRegistration::latest()->first()->created_at ?? null,
                'last_attendance' => ConferenceRegistration::where('attended', true)
                    ->latest('updated_at')
                    ->first()->updated_at ?? null,
                'last_payment' => ConferenceRegistration::where('payment_status', 'completed')
                    ->latest('payment_date')
                    ->first()->payment_date ?? null
            ]
        ], 200);
    } catch (\Exception $e) {
        Log::error('Conference stats retrieval failed: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Unable to fetch conference stats'], 500);
    }
}

/**
 * Get payment statistics
 */
public function getPaymentStats()
{
    try {
        // Daily payment stats for last 30 days
        $dailyStats = ConferenceRegistration::where('payment_status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Category payment stats
        $categoryStats = ConferenceRegistration::where('payment_status', 'completed')
            ->selectRaw('category, COUNT(*) as count, SUM(total_amount) as amount')
            ->groupBy('category')
            ->get();

        // Payment method stats (if you have payment_method column)
        $paymentMethodStats = ConferenceRegistration::where('payment_status', 'completed')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as amount')
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'success' => true,
            'stats' => [
                'daily_payments' => $dailyStats,
                'category_breakdown' => $categoryStats,
                'payment_methods' => $paymentMethodStats,
                'summary' => [
                    'total_collected' => ConferenceRegistration::where('payment_status', 'completed')->sum('total_amount'),
                    'average_transaction' => ConferenceRegistration::where('payment_status', 'completed')->avg('total_amount'),
                    'highest_transaction' => ConferenceRegistration::where('payment_status', 'completed')->max('total_amount'),
                    'lowest_transaction' => ConferenceRegistration::where('payment_status', 'completed')->min('total_amount'),
                ]
            ]
        ], 200);
    } catch (\Exception $e) {
        Log::error('Payment stats retrieval failed: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Unable to fetch payment stats'], 500);
    }
}
}