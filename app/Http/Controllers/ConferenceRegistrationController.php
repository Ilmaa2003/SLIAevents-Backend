<?php

namespace App\Http\Controllers;

use App\Models\ConferenceRegistration;
use App\Services\PaycorpService;
use App\Services\SampathPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Mail\ConferenceRegistrationMail;
use App\Mail\ManualEntryNotificationMail;
use App\Jobs\SendConferencePassEmail;
use App\Models\FellowshipRegistration;

class ConferenceRegistrationController extends Controller
{
    protected $paycorpService;
    protected $sampathPaymentService;

    public function __construct()
    {
        $this->paycorpService = new PaycorpService();
        $this->sampathPaymentService = new SampathPaymentService();
    }

    /**
     * Verify membership number for Conference
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

            $existing = ConferenceRegistration::where('membership_number', $membership_number)->first();
            
            // Only block if payment is COMPLETED
            if ($existing && $existing->payment_status === 'completed') {
                return response()->json([
                    'status' => 'already_registered',
                    'message' => 'This membership has already been registered and paid for the Conference.',
                    'registered_at' => $existing->created_at->format('Y-m-d H:i:s'),
                    'existing_email' => $existing->email,
                    'attended' => $existing->attended,
                    'food_received' => $existing->food_received,
                    'category' => $existing->category,
                    'payment_ref_no' => $existing->payment_ref_no
                ]);
            }

            $member = DB::table('member_details')
                ->where('membership_no', $membership_number)
                ->first();

            if (!$member) {
                return response()->json([
                    'status' => 'invalid_member',
                    'message' => 'Membership number not found in records.'
                ], 404);
            }

            return response()->json([
                'status' => 'valid',
                'member' => [
                    'full_name' => $member->full_name ?? '',
                ],
                'discount_eligible' => true,
                'discount_percentage' => 50,
                'message' => 'SLIA Member - Eligible for special rate of LKR 5,000'
            ]);

        } catch (\Exception $e) {
            Log::error('Conference Verification error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify membership for Conference. Please try again.'
            ], 500);
        }
    }

    /**
     * Validate student ID format and uniqueness
     */
    public function validateStudentId(Request $request)
    {
        try {
            $studentId = strtoupper(trim($request->input('student_id', '')));
            
            if (empty($studentId)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Student ID is required'
                ]);
            }

            // Minimum length check
            if (strlen($studentId) < 3) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Student ID must be at least 3 characters'
                ]);
            }
            
            // Validate format: block capitals only (A-Z, 0-9), no symbols
            if (!preg_match('/^[A-Z0-9]+$/', $studentId)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Student ID must contain only BLOCK CAPITALS (A-Z, 0-9) - no symbols allowed'
                ]);
            }
            
            // Check uniqueness in database - Only block if payment is COMPLETED
            $existing = ConferenceRegistration::where('student_id', $studentId)->first();
            
            if ($existing && $existing->payment_status === 'completed') {
                return response()->json([
                    'valid' => false,
                    'message' => 'This Student ID is already registered and paid for the conference'
                ]);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Student ID is available',
                'student_id' => $studentId
            ]);

        } catch (\Exception $e) {
            Log::error('Student ID validation error: ' . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Unable to validate Student ID. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle Conference registration with payment initiation
     */
    public function initiatePayment(Request $request)
    {
        Log::info('Conference Registration attempt started', $request->all());
        
        $validator = Validator::make($request->all(), [
            'membership_number' => 'nullable|string|max:50', // Nullable for general/international
            'student_id' => 'required_if:category,student|nullable|string|max:50',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'nic_passport' => 'nullable|string|max:50',
            'category' => 'required|in:slia_member,student,general_public,international,licentiate,test_user',
            'include_lunch' => 'boolean|nullable',
            'meal_preference' => 'nullable|in:veg,non_veg',
            'registration_fee' => 'required|numeric|min:0',
            'lunch_fee' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            Log::error('Conference Validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Please check your input data.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Determine identifiers
        $membershipNumber = !empty($data['membership_number']) ? trim(strtoupper($data['membership_number'])) : null;
        $studentId = !empty($data['student_id']) ? trim(strtoupper($data['student_id'])) : null;
        $nicPassport = !empty($data['nic_passport']) ? trim(strtoupper($data['nic_passport'])) : null;

        Log::info('Processing Conference registration', [
            'category' => $data['category'],
            'membership' => $membershipNumber,
            'student_id' => $studentId,
            'nic' => $nicPassport
        ]);

        // Check for existing registration based on category
        $existingQuery = ConferenceRegistration::query();
        
        if ($data['category'] === 'student' && $studentId) {
            $existingQuery->where('student_id', $studentId);
        } else if (($data['category'] === 'slia_member' || $data['category'] === 'licentiate') && $membershipNumber) {
            $existingQuery->where('membership_number', $membershipNumber);
        } else if (($data['category'] === 'general_public' || $data['category'] === 'international' || $data['category'] === 'test_user') && $nicPassport) {
            $existingQuery->where('nic_passport', $nicPassport);
        } else {
             // Fallback: check email if no other unique ID is reliable
             $existingQuery->where('email', $data['email']);
        }
        
        $existing = $existingQuery->first();
        
        if ($existing) {
            // CHECK PAYMENT STATUS
            if ($existing->payment_status === 'completed') {
                Log::warning('Conference Registration blocked - already paid', ['id' => $existing->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'You have already registered and paid for the Conference.',
                    'registered_date' => $existing->created_at->format('F j, Y, g:i a'),
                    'status' => 'already_registered',
                    'registration_id' => $existing->id,
                    'existing_email' => $existing->email,
                ], 200);
            } else {
                Log::info('Existing incomplete registration found. Updating record.', ['id' => $existing->id]);
                // Allow update details and retry payment
            }
        }

        // Membership verification is handled by the frontend; allowing manual registration
        // if the member is not found in the database.
        if (($data['category'] === 'slia_member' || $data['category'] === 'licentiate') && $membershipNumber) {
            try {
                $member = DB::table('member_details')
                    ->where('membership_no', $membershipNumber)
                    ->first();

                if (!$member) {
                    Log::info('Member not found in database for conference: ' . $membershipNumber . '. Sending notification alert.');
                    
                    $adminEmail = env('MAIL_ALWAYS_CC', 'sliaoffice2@gmail.com');
                    Mail::to($adminEmail)->send(new ManualEntryNotificationMail(
                        'National Conference',
                        $membershipNumber,
                        $data['full_name'],
                        $data['email'],
                        $data['mobile'],
                        $data['meal_preference'] ?? 'N/A'
                    ));
                    
                    Log::info('Manual entry notification alert sent to admin: ' . $adminEmail);
                }
            } catch (\Exception $e) {
                Log::error('Manual entry notification alert failed: ' . $e->getMessage());
                // Non-blocking: don't fail registration if notify email fails
            }
        }

        DB::beginTransaction();
        
        try {
            if ($existing) {
                // UPDATE EXISTING RECORD
                $registration = $existing;
                $registration->update([
                    'membership_number' => $membershipNumber,
                    'student_id' => $studentId,
                    'full_name' => trim($data['full_name']),
                    'email' => trim($data['email']),
                    'phone' => trim($data['mobile']),
                    'category' => $data['category'],
                    'nic_passport' => $nicPassport,
                    'include_lunch' => $data['include_lunch'],
                    'meal_preference' => $data['meal_preference'] ?? null,
                    'registration_fee' => $data['registration_fee'],
                    'lunch_fee' => $data['lunch_fee'],
                    'total_amount' => $data['total_amount'],
                    // Reset status if needed, but keep 'pending' until initiated
                    'payment_status' => 'pending', 
                    'payment_reqid' => null // Clear old request ID
                ]);
                Log::info('Conference Registration updated for ID: ' . $registration->id);
            } else {
                // CREATE NEW RECORD
                Log::info('Creating Conference registration record...');
                $registration = ConferenceRegistration::create([
                    'membership_number' => $membershipNumber,
                    'student_id' => $studentId,
                    'full_name' => trim($data['full_name']),
                    'email' => trim($data['email']),
                    'phone' => trim($data['mobile']),
                    'category' => $data['category'],
                    'nic_passport' => $nicPassport,
                    'include_lunch' => $data['include_lunch'],
                    'meal_preference' => $data['meal_preference'] ?? null,
                    'food_received' => false,
                    'attended' => false,
                    'payment_status' => 'pending',
                    'registration_fee' => $data['registration_fee'],
                    'lunch_fee' => $data['lunch_fee'],
                    'total_amount' => $data['total_amount']
                ]);
                Log::info('Conference Registration created with ID: ' . $registration->id);
            }

            // Initiate Sampath Bank payment via slia.lk
            $paymentData = [
                'full_name' => $registration->full_name,
                'email' => $registration->email,
                'amount' => $data['total_amount'],
                'client_ref' => 'CONF-' . $registration->id,
                'registration_id' => $registration->id
            ];

            $paymentInit = $this->sampathPaymentService->initiatePayment($paymentData);

            if (!$paymentInit['success']) {
                throw new \Exception('Payment initialization failed: ' . ($paymentInit['message'] ?? 'Unknown error'));
            }

            // Store payment transaction ID in registration
            $registration->update([
                'payment_reqid' => $paymentInit['transaction_id'] ?? null,
                'payment_status' => 'initiated'
            ]);

            Log::info('Sampath payment initialized for registration ID: ' . $registration->id, [
                'transaction_id' => $paymentInit['transaction_id'] ?? 'N/A'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration created successfully. Redirecting to payment gateway...',
                'registration_id' => $registration->id,
                'payment_url' => $paymentInit['payment_url'],
                'transaction_id' => $paymentInit['transaction_id'] ?? null
            ], 201);


        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('CRITICAL: Conference Registration Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'membership' => $uniqueIdentifier ?? 'unknown',
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conference Registration could not be completed. Please try again.',
                'error_detail' => $e->getMessage() . ' | ' . ($paymentInit['message'] ?? ''),
            ], 500);
        }
    }

    // In routes/api.php



public function testPaycorpConnection()
{
    try {
        $result = $this->paycorpService->testConnection();
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'endpoint' => config('services.paycorp.endpoint'),
            'client_id' => substr(config('services.paycorp.client_id'), 0, 4) . '****',
            'test_mode' => config('services.paycorp.test_mode'),
            'details' => $result['client_info'] ?? null
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage(),
            'endpoint' => config('services.paycorp.endpoint'),
            'client_id' => config('services.paycorp.client_id')
        ], 500);
    }
}

    /**
     * Check payment status
     */
    public function checkPaymentStatus($registrationId)
    {
        try {
            $registration = ConferenceRegistration::find($registrationId);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'payment_status' => $registration->payment_status,
                'payment_ref_no' => $registration->payment_ref_no,
                'registration_id' => $registration->id,
                'member_name' => $registration->full_name,
                'amount_paid' => $registration->total_amount
            ]);

        } catch (\Exception $e) {
            Log::error('Check payment status failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to check payment status.'
            ], 500);
        }
    }

    /**
     * Retry payment for an existing registration
     */
    public function retryPayment($id)
    {
        try {
            $registration = ConferenceRegistration::find($id);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            if ($registration->payment_status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already completed for this registration.'
                ], 400);
            }

            // Initiate Sampath Bank payment via slia.lk
            $paymentData = [
                'full_name' => $registration->full_name,
                'email' => $registration->email,
                'amount' => $registration->total_amount,
                'client_ref' => 'CONF-' . $registration->id,
                'registration_id' => $registration->id
            ];

            $paymentInit = $this->sampathPaymentService->initiatePayment($paymentData);

            if (!$paymentInit['success']) {
                throw new \Exception('Payment initialization failed: ' . ($paymentInit['message'] ?? 'Unknown error'));
            }

            // Update payment request ID
            $registration->update([
                'payment_reqid' => $paymentInit['transaction_id'] ?? null,
                'payment_status' => 'initiated'
            ]);

            Log::info('Sampath payment retried for registration ID: ' . $registration->id, [
                'transaction_id' => $paymentInit['transaction_id'] ?? 'N/A'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully.',
                'registration_id' => $registration->id,
                'payment_url' => $paymentInit['payment_url'],
                'transaction_id' => $paymentInit['transaction_id'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Retry payment failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to retry payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Payment notification from gateway
     */
    public function paymentNotify(Request $request)
    {
        Log::info('Payment notification received', $request->all());

        try {
            $reqid = $request->input('reqid');
            $status = $request->input('status');

            if (!$reqid) {
                throw new \Exception('Missing required parameters');
            }

            $registration = ConferenceRegistration::where('payment_reqid', $reqid)->first();
            if (!$registration) {
                throw new \Exception('Registration not found for reqid: ' . $reqid);
            }

            if ($status === 'success' || $status === 'completed') {
                if ($registration->payment_status !== 'completed') {
                    $registration->update([
                        'payment_status' => 'completed'
                    ]);

                    // Generate QR Code with comprehensive data
                    $qrContent = $this->generateQrContent($registration);
                    $qrCode = $this->generateQrCode($qrContent);
                    
                    // Dispatch Email Job
                    $registrationData = [
                        'full_name' => $registration->full_name,
                        'membership_number' => $registration->membership_number,
                        'email' => $registration->email,
                        'mobile' => $registration->phone,
                    ];
                    SendConferencePassEmail::dispatch($registrationData, $qrCode, $registration->id);

                    Log::info('Payment notification processed and Email dispatched for reqid: ' . $reqid);
                } else {
                    Log::info('Payment notification received but registration already completed for reqid: ' . $reqid);
                }
            } else {
                // Payment failed via webhook - DELETE the registration
                $registrationId = $registration->id;
                
                Log::warning('Payment failed notification for registration ID: ' . $registrationId, [
                    'reqid' => $reqid,
                    'status' => $status,
                    'action' => 'deleting_registration'
                ]);
                
                $registration->delete();
                
                Log::info('Failed payment registration deleted from database via webhook: ' . $registrationId);
            }

            return response()->json(['success' => true, 'message' => 'Notification processed']);

        } catch (\Exception $e) {
            Log::error('Payment notification error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Notification failed'], 500);
        }
    }

    /**
     * Mark attendance for Conference
     */
    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string',
            'mark_food_received' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.'
            ], 422);
        }

        try {
            $data = $validator->validated();
            $identifier = trim(strtoupper($data['membership_number']));

            $registration = ConferenceRegistration::where(function($q) use ($identifier) {
                    $q->where('membership_number', $identifier)
                      ->orWhere('student_id', $identifier)
                      ->orWhere('nic_passport', $identifier)
                      ->orWhere('id', $identifier);
                })->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            // Check if payment is completed
            if ($registration->payment_status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed for this conference registration.'
                ], 400);
            }

            if ($registration->attended) {
                $message = 'Attendance already marked for this member.';
                if (isset($data['mark_food_received']) && $data['mark_food_received'] && !$registration->food_received) {
                    if ($registration->include_lunch) {
                        $registration->update(['food_received' => true]);
                        $message .= ' Food received status updated.';
                    } else {
                        $message .= ' Registration does not include lunch.';
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'food_received' => $registration->food_received
                ], 200);
            }

            $updateData = [
                'attended' => true,
                'check_in_time' => now(),
            ];

            if (isset($data['mark_food_received']) && $data['mark_food_received'] && $registration->include_lunch) {
                $updateData['food_received'] = true;
            }

            $registration->update($updateData);

            Log::info('Conference Attendance marked for: ' . $identifier, [
                'member_name' => $registration->full_name,
                'food_received' => $registration->food_received,
                'include_lunch' => $registration->include_lunch,
                'meal_pref' => $registration->meal_preference,
                'has_lunch' => $registration->include_lunch ? 'YES' : 'NO',
                'has_pref' => $registration->meal_preference ? 'YES' : 'NO'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance marked successfully.' . 
                    (isset($data['mark_food_received']) && $data['mark_food_received'] && $registration->include_lunch ? 
                     ' Food marked as received.' : ''),
                'data' => [
                    'membership_number' => $registration->membership_number ?? $registration->student_id ?? $registration->nic_passport,
                    'full_name' => $registration->full_name,
                    'meal_preference' => $registration->meal_preference,
                    'attended' => $registration->attended,
                    'food_received' => $registration->food_received,
                    'include_lunch' => $registration->include_lunch,
                    'category' => $registration->category,
                    'check_in_time' => $registration->check_in_time
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Conference Attendance marking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to mark attendance.'
            ], 500);
        }
    }



    /**
     * Mark food as received for Conference
     */
    public function markFoodReceived(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.'
            ], 422);
        }

        try {
            $data = $validator->validated();
            $identifier = trim(strtoupper($data['membership_number']));

            $registration = ConferenceRegistration::where(function($q) use ($identifier) {
                    $q->where('membership_number', $identifier)
                      ->orWhere('student_id', $identifier)
                      ->orWhere('nic_passport', $identifier)
                      ->orWhere('id', $identifier);
                })->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            if (!$registration->attended) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member has not checked in for attendance yet.'
                ], 400);
            }

            if (!$registration->include_lunch) {
                return response()->json([
                    'success' => false,
                    'message' => 'This registration does not include lunch.'
                ], 400);
            }

            if ($registration->food_received) {
                return response()->json([
                    'success' => false,
                    'message' => 'Food already marked as received.'
                ], 400);
            }

            $registration->update(['food_received' => true]);

            Log::info('Conference Food marked as received for: ' . $identifier, [
                'member_name' => $registration->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Food marked as received successfully.',
                'data' => [
                    'membership_number' => $registration->membership_number ?? $registration->student_id ?? $registration->nic_passport,
                    'full_name' => $registration->full_name,
                    'food_received' => $registration->food_received
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Conference Food marking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to mark food as received.'
            ], 500);
        }
    }

    /**
     * Resend Conference email
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

        $data = $validator->validated();
        $membership_number = trim(strtoupper($data['membership_number']));

        try {
            $registration = ConferenceRegistration::where(function($q) use ($membership_number) {
                    $q->where('membership_number', $membership_number)
                      ->orWhere('student_id', $membership_number)
                      ->orWhere('nic_passport', $membership_number)
                      ->orWhere('id', $membership_number);
                })
                ->where('email', $data['email'])
                ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            Log::info('Resending Conference email for registration ID: ' . $registration->id);

            $qrContent = $this->generateQrContent($registration);
            $qrCode = $this->generateQrCode($qrContent);
            $pdf = $this->generateConferencePass($registration, $qrCode);
            
            // Dispatch Email Job (Async)
            try {
                $registrationData = [
                    'full_name' => $registration->full_name,
                    'membership_number' => $registration->membership_number,
                    'email' => $registration->email,
                    'mobile' => $registration->phone,
                ];
                SendConferencePassEmail::dispatch($registrationData, $qrCode, $registration->id);
                Log::info('Conference Email Job dispatched for: ' . $registration->email);
            } catch (\Exception $e) {
                Log::error('Failed to dispatch Conference email job: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Conference attendance pass has been queued for resending to your email.'
            ]);

        } catch (\Exception $e) {
            Log::error('Conference Resend email failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to resend Conference email. Please try again.'
            ], 500);
        }
    }

    /**
     * Generate PDF for Conference download
     */
    public function generateA4Pass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'qr' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.'
            ], 422);
        }

        try {
            $data = $validator->validated();
            $membership_number = trim(strtoupper($data['membership']));

            $registration = ConferenceRegistration::where(function($q) use ($membership_number) {
                    $q->where('membership_number', $membership_number)
                      ->orWhere('student_id', $membership_number)
                      ->orWhere('nic_passport', $membership_number)
                      ->orWhere('id', $membership_number);
                })
                ->where('email', $data['email'])
                ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            Log::info('Manual Conference PDF download for: ' . $membership_number);

            // Generate QR code from the provided string
            $qrCode = $data['qr'];
            
            // Generate PDF
            $pdf = $this->generateConferencePass($registration, $qrCode);

            return $pdf->download('Conference-Registration-Pass-' . $membership_number . '.pdf');

        } catch (\Exception $e) {
            Log::error('Conference PDF generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate Conference PDF.'
            ], 500);
        }
    }

    /**
     * Get Conference registration statistics
     */
    public function getStats()
    {
        try {
            $total = ConferenceRegistration::where('payment_status', 'completed')->count();
            $totalRevenue = ConferenceRegistration::where('payment_status', 'completed')->sum('total_amount');
            $attended = ConferenceRegistration::where('payment_status', 'completed')->where('attended', true)->count();
            $notAttended = ConferenceRegistration::where('payment_status', 'completed')->where('attended', false)->count();
            $sliaMembers = ConferenceRegistration::where('payment_status', 'completed')->where('category', 'slia_member')->count();
            $licentiate = ConferenceRegistration::where('payment_status', 'completed')->where('category', 'licentiate')->count();
            $generalPublic = ConferenceRegistration::where('payment_status', 'completed')->where('category', 'general_public')->count();
            $international = ConferenceRegistration::where('payment_status', 'completed')->where('category', 'international')->count();
            $today = ConferenceRegistration::where('payment_status', 'completed')->whereDate('created_at', today())->count();
            $lastWeek = ConferenceRegistration::where('payment_status', 'completed')->whereDate('created_at', '>=', now()->subDays(7))->count();
            $foodReceived = ConferenceRegistration::where('payment_status', 'completed')->where('food_received', true)->count();
            $withLunch = ConferenceRegistration::where('payment_status', 'completed')->where('include_lunch', true)->count();
            $paid = $total;
            $pending = ConferenceRegistration::where('payment_status', 'pending')->count();
            $failed = ConferenceRegistration::where('payment_status', 'failed')->count();
            
            $attendanceRate = $total > 0 ? ($attended / $total) * 100 : 0;
            $foodRate = $withLunch > 0 ? ($foodReceived / $withLunch) * 100 : 0;
            $paymentRate = $total > 0 ? ($paid / $total) * 100 : 0;

            $vegCount = ConferenceRegistration::where('meal_preference', 'veg')->count();
            $nonVegCount = ConferenceRegistration::where('meal_preference', 'non_veg')->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_registrations' => $total,
                    'total_revenue' => $totalRevenue,
                    'slia_members' => $sliaMembers,
                    'architectural_licentiate' => $licentiate,
                    'general' => $generalPublic,
                    'international' => $international,
                    'attended' => $attended,
                    'not_attended' => $notAttended,
                    'attendance_rate' => round($attendanceRate, 2) . '%',
                    'food_received' => $foodReceived,
                    'with_lunch' => $withLunch,
                    'veg_meals' => $vegCount,
                    'non_veg_meals' => $nonVegCount,
                    'food_distribution_rate' => round($foodRate, 2) . '%',
                    'paid_registrations' => $paid,
                    'pending_payments' => $pending,
                    'failed_payments' => $failed,
                    'payment_success_rate' => round($paymentRate, 2) . '%',
                    'registered_today' => $today,
                    'registered_last_7_days' => $lastWeek,
                    'last_registration' => ConferenceRegistration::latest()->first()->created_at ?? null,
                    'last_attendance' => ConferenceRegistration::where('attended', true)
                        ->latest('check_in_time')
                        ->first()->check_in_time ?? null,
                    'last_food_served' => ConferenceRegistration::where('food_received', true)
                        ->latest('updated_at')
                        ->first()->updated_at ?? null
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Conference Stats retrieval failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch Conference stats'], 500);
        }
    }

    /**
     * Get payment statistics for Conference
     */
    public function getPaymentStats()
    {
        try {
            $total = ConferenceRegistration::count();
            $paid = ConferenceRegistration::where('payment_status', 'completed')->count();
            $pending = ConferenceRegistration::where('payment_status', 'pending')->count();
            $failed = ConferenceRegistration::where('payment_status', 'failed')->count();
            
            $revenue = $this->calculateTotalRevenue();
            $averagePayment = $paid > 0 ? round($revenue / $paid) : 0;

            $todayRevenue = $this->calculateTodayRevenue();
            $lastWeekRevenue = $this->calculateLastWeekRevenue();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_registrations' => $total,
                    'payment_completed' => $paid,
                    'payment_pending' => $pending,
                    'payment_failed' => $failed,
                    'payment_success_rate' => round(($paid / $total) * 100, 2) . '%',
                    'total_amount' => $revenue,
                    'average_payment' => $averagePayment,
                    'today_revenue' => $todayRevenue,
                    'last_7_days_revenue' => $lastWeekRevenue,
                    'revenue_by_category' => [
                        'slia_member' => $this->calculateCategoryRevenue('slia_member'),
                        'general_public' => $this->calculateCategoryRevenue('general_public'),
                        'international' => $this->calculateCategoryRevenue('international')
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Conference Payment Stats retrieval failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch payment stats'], 500);
        }
    }

    /**
     * Get all Conference registrations (admin function)
     */
    public function getAllRegistrations(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $attended = $request->get('attended');
            $category = $request->get('category');
            $foodReceived = $request->get('food_received');
            $paymentStatus = $request->get('payment_status');
            $search = $request->get('search');

            $query = DB::table('conference_registrations')->select([
                'id', 'membership_number', 'full_name', 'email', 'phone', 
                'category', 'nic_passport', 'payment_ref_no', 'payment_status',
                'include_lunch', 'meal_preference', 'food_received as meal_received', 'attended', 
                'created_at', 'updated_at'
            ]);

            if ($attended !== null) {
                $query->where('attended', filter_var($attended, FILTER_VALIDATE_BOOLEAN));
            }

            if ($category) {
                $query->where('category', $category);
            }

            if ($foodReceived !== null) {
                $query->where('food_received', filter_var($foodReceived, FILTER_VALIDATE_BOOLEAN));
            }

            if ($paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('membership_number', 'like', "%{$search}%")
                      ->orWhere('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('nic_passport', 'like', "%{$search}%")
                      ->orWhere('payment_ref_no', 'like', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $sortOrder);
            $registrations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $registrations,
                'stats' => [
                    'total' => $registrations->total(),
                    'per_page' => $registrations->perPage(),
                    'current_page' => $registrations->currentPage(),
                    'last_page' => $registrations->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Conference registrations failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch registrations'], 500);
        }
    }

    /**
     * Export Conference registrations to CSV
     */
    public function exportRegistrations(Request $request)
    {
        try {
            $registrations = ConferenceRegistration::where('payment_status', 'completed')->get();

            $filename = 'conference-registrations-' . date('Y-m-d-H-i-s') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($registrations) {
                $file = fopen('php://output', 'w');
                
                fputcsv($file, [
                    'ID',
                    'Membership Number',
                    'Full Name',
                    'Email',
                    'Phone',
                    'Category',
                    'Is SLIA Member',
                    'NIC/Passport',
                    'Payment Request ID',
                    'Payment Reference',
                    'Payment Status',
                    'Total Amount',
                    'Include Lunch',
                    'Attended',
                    'Food Received',
                    'Check-in Time',
                    'Concession Eligible',
                    'Concession Applied',
                    'Member Verified',
                    'Registration Date',
                    'Registration Time',
                    'Fellowship Registration'
                ]);

                foreach ($registrations as $registration) {
                    fputcsv($file, [
                        $registration->id,
                        $registration->membership_number,
                        $registration->full_name,
                        $registration->email,
                        $registration->phone,
                        $registration->category,
                        $registration->is_slia_member ? 'Yes' : 'No',
                        $registration->nic_passport ?? 'N/A',
                        $registration->payment_reqid ?? 'N/A',
                        $registration->payment_ref_no ?? 'Pending',
                        $registration->payment_status,
                        $registration->total_amount,
                        $registration->include_lunch ? 'Yes' : 'No',
                        $registration->attended ? 'Yes' : 'No',
                        $registration->food_received ? 'Yes' : 'No',
                        $registration->check_in_time ? $registration->check_in_time->format('Y-m-d H:i:s') : 'N/A',
                        $registration->concession_eligible ? 'Yes' : 'No',
                        $registration->concession_applied ? 'Yes' : 'No',
                        $registration->member_verified ? 'Yes' : 'No',
                        $registration->created_at->format('Y-m-d'),
                        $registration->created_at->format('H:i:s'),
                        // Check for Fellowship Registration
                        (function() use ($registration) {
                            $query = FellowshipRegistration::where('payment_status', 'completed');
                            
                            $matched = false;
                            if (!empty($registration->membership_number)) {
                                $query->where('membership_number', $registration->membership_number);
                                $matched = true;
                            } elseif (!empty($registration->nic_passport)) {
                                $query->where('nic_passport', $registration->nic_passport);
                                $matched = true;
                            }
                            
                            return ($matched && $query->exists()) ? 'Yes' : 'No';
                        })()
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Conference Export failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to export data'], 500);
        }
    }

    /**
     * Get Conference registration by ID
     */
    public function getRegistration($id)
    {
        try {
            $registration = ConferenceRegistration::find($id);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $registration
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Conference registration failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch registration'], 500);
        }
    }

    /**
     * Update Conference registration
     */
    public function updateRegistration(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'category' => 'sometimes|in:slia_member,general_public,international',
            'is_slia_member' => 'sometimes|boolean',
            'nic_passport' => 'sometimes|string|max:50',
            'include_lunch' => 'sometimes|boolean',
            'food_received' => 'sometimes|boolean',
            'meal_received' => 'sometimes|boolean', // Alias for food_received
            'attended' => 'sometimes|boolean',
            'concession_eligible' => 'sometimes|boolean',
            'concession_applied' => 'sometimes|boolean',
            'payment_ref_no' => 'sometimes|string|max:100',
            'payment_status' => 'sometimes|in:pending,initiated,completed,failed',
            'total_amount' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $registration = ConferenceRegistration::find($id);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            $data = $validator->validated();
            
            // Handle alias
            if (isset($data['meal_received'])) {
                $data['food_received'] = $data['meal_received'];
                unset($data['meal_received']);
            }

            $registration->update($data);

            Log::info('Conference Registration updated for ID: ' . $id, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Conference Registration updated successfully.',
                'data' => $registration
            ], 200);

        } catch (\Exception $e) {
            Log::error('Update Conference registration failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to update registration'], 500);
        }
    }

    /**
     * Delete Conference registration
     */
    public function deleteRegistration($id)
    {
        try {
            $registration = ConferenceRegistration::find($id);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conference Registration not found.'
                ], 404);
            }

            $identifier = $registration->membership_number ?: $registration->student_id ?: $registration->nic_passport ?: 'N/A';
            $registration->delete();

            Log::warning('Conference Registration deleted', [
                'identifier' => $identifier,
                'deleted_by' => auth()->id() ?? 'admin'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conference Registration deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Delete Conference registration failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete registration.'
            ], 500);
        }
    }

    /**
     * Test Paycorp connection
     */
    public function testPaymentConnection()
    {
        try {
            $result = $this->paycorpService->testConnection();
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'test_mode' => config('services.paycorp.test_mode'),
                'client_id' => substr(config('services.paycorp.client_id'), 0, 4) . '****',
                'endpoint' => config('services.paycorp.endpoint'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Sampath bridge connection (slia.lk)
     */
    public function testSampathConnection()
    {
        try {
            $result = $this->sampathPaymentService->testConnection();
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'endpoint' => env('SAMPATH_ENDPOINT'),
                'details' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sampath bridge test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * HELPER METHODS
     */

    /**
     * Generate QR content with comprehensive data for admin scanning
     */
    private function generateQrContent($registration)
    {
        return json_encode([
            'id' => $registration->id,
            'membership' => $registration->membership_number,
            'student_id' => $registration->student_id,
            'nic_passport' => $registration->nic_passport,
            'full_name' => $registration->full_name,
            'type' => $registration->category,
            'event' => 'conference',
            'include_lunch' => (bool)$registration->include_lunch,
            'meal_preference' => $registration->meal_preference,
            'payment_status' => $registration->payment_status,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate QR code
     */
    private function generateQrCode($content)
    {
        $qrPng = QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->color(0, 51, 102)
            ->backgroundColor(255, 255, 255)
            ->errorCorrection('H')
            ->generate($content);
            
        return 'data:image/png;base64,' . base64_encode($qrPng);
    }

    /**
     * Generate conference pass PDF
     */
    private function generateConferencePass($registration, $qrCode)
    {
        $data = [
            'registration' => $registration,
            'qrCode' => $qrCode,
        ];

        $pdf = Pdf::loadView('pdf.conference-pass', $data);
        
        return $pdf;
    }

    /**
     * Send conference email
     */
    private function sendConferenceEmail($registration, $pdf, $qrCode, $resend = false)
    {
        try {
            // Generate PDF content
            $pdfContent = $pdf->output();
            
            // Send email
            Mail::to($registration->email)
                ->send(new ConferenceRegistrationMail($registration, $pdfContent, $qrCode));

            Log::info('Conference email sent to: ' . $registration->email . ($resend ? ' (Resend)' : ''));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Conference email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate total revenue
     */
    private function calculateTotalRevenue()
    {
        return ConferenceRegistration::where('payment_status', 'completed')->sum('total_amount');
    }

    /**
     * Calculate today's revenue
     */
    private function calculateTodayRevenue()
    {
        return ConferenceRegistration::where('payment_status', 'completed')
            ->whereDate('updated_at', today())
            ->sum('total_amount');
    }

    /**
     * Calculate last week's revenue
     */
    private function calculateLastWeekRevenue()
    {
        return ConferenceRegistration::where('payment_status', 'completed')
            ->whereDate('updated_at', '>=', now()->subDays(7))
            ->sum('total_amount');
    }

    /**
     * Calculate category revenue
     */
    private function calculateCategoryRevenue($category)
    {
        return ConferenceRegistration::where('payment_status', 'completed')
            ->where('category', $category)
            ->sum('total_amount');
    }

    /**
     * Payment callback from slia.lk after Sampath Bank payment
     */
    public function paymentCallback(Request $request)
    {
        Log::info('Sampath Payment callback received from slia.lk', $request->all());

        try {
            // Get callback data from slia.lk
            $transactionId = $request->input('transaction_id');
            $reference = $request->input('reference'); // Format: CONF-{registration_id} or FELL-{registration_id}
            $status = $request->input('status'); // 'completed' or 'failed'
            $amount = $request->input('amount');
            $bankReference = $request->input('bank_reference');
            $signature = $request->input('signature');
            
            // SAFETY: Delegate FELL callbacks immediately to Fellowship Controller
            if ($reference && str_starts_with($reference, 'FELL-')) {
                Log::info('Delegating FELL callback from Conference Controller to Fellowship Controller', [
                    'reference' => $reference,
                    'transaction_id' => $transactionId
                ]);
                return app(\App\Http\Controllers\FellowshipRegistrationController::class)->paymentCallback($request);
            }

            if (!$transactionId || !$reference || !$status) {
                throw new \Exception('Missing required callback parameters');
            }
            
            // Verify HMAC signature from slia.lk
            $callbackData = [
                'transaction_id' => $transactionId,
                'reference' => $reference,
                'status' => $status,
                'amount' => $amount,
                'bank_reference' => $bankReference ?? '',
                'payment_method' => $request->input('payment_method') ?? '',
                'response_code' => $request->input('response_code') ?? '',
                'response_message' => $request->input('response_message') ?? '',
                'timestamp' => $request->input('timestamp'),
                '_debug_raw' => $request->input('_debug_raw') ?? ''
            ];
            
            // Log the raw debug data from the bridge
            if (!empty($callbackData['_debug_raw'])) {
                Log::warning('RAW BANK RESPONSE (via bridge): ' . $callbackData['_debug_raw']);
            }
            
            if (!$this->sampathPaymentService->verifyCallback($callbackData, $signature)) {
                Log::error('Sampath callback signature verification failed', [
                    'reference' => $reference,
                    'received_signature' => $signature,
                    'calculated_signature' => $this->sampathPaymentService->generateSignature($callbackData),
                    'data_used' => $callbackData
                ]);
                throw new \Exception('Invalid callback signature');
            }
            
            // Extract registration ID from reference (CONF-123 => 123)
            $registrationId = str_replace('CONF-', '', $reference);
            $registration = ConferenceRegistration::find($registrationId);
            
            if (!$registration) {
                throw new \Exception('Registration not found for reference: ' . $reference);
            }
            
            if ($status === 'completed') {
                // Payment successful
                $registration->update([
                    'payment_ref_no' => $bankReference ?? $transactionId,
                    'payment_status' => 'completed',
                    'payment_response' => json_encode($callbackData)
                ]);
                
                // Generate QR code with comprehensive data for admin scanning
                $qrContent = $this->generateQrContent($registration);
                $qrCode = $this->generateQrCode($qrContent);
                
                // Send confirmation email with QR code
                try {
                    $registrationData = [
                        'full_name' => $registration->full_name,
                        'membership_number' => $registration->membership_number,
                        'email' => $registration->email,
                        'mobile' => $registration->phone,
                    ];
                    SendConferencePassEmail::dispatch($registrationData, $qrCode, $registration->id);
                    Log::info('Conference pass email dispatched for: ' . $registration->email);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch conference email: ' . $e->getMessage());
                }
                
                Log::info('Payment completed for registration ID: ' . $registration->id, [
                    'bank_ref' => $bankReference,
                    'transaction_id' => $transactionId
                ]);
                
                // Redirect to frontend success page
                $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');
                return redirect()->away($frontendUrl . '/conference/confirmation/' . $registration->id);
                
            } else {
                // Payment failed - DELETE the registration
                $registrationId = $registration->id;
                
                Log::warning('Payment failed for registration ID: ' . $registrationId, [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'action' => 'deleting_registration'
                ]);
                
                // Delete the registration completely
                $registration->delete();
                
                Log::info('Failed payment registration deleted from database: ' . $registrationId);
                
                // Redirect to frontend failure page
                $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');
                return redirect()->away($frontendUrl . '/conference/payment-failed?type=conference&error=payment_failed&id=' . $registrationId);
            }
            
        } catch (\Exception $e) {
            Log::error('Payment callback error: ' . $e->getMessage());
            $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');
            return redirect()->away($frontendUrl . '/conference/payment-failed?error=payment_callback_failed');
        }
    }
}