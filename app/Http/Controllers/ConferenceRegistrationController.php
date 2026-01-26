<?php

namespace App\Http\Controllers;

use App\Models\ConferenceRegistration;
use App\Services\PaycorpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Mail\ConferenceRegistrationMail;

class ConferenceRegistrationController extends Controller
{
    protected $paycorpService;

    public function __construct()
    {
        $this->paycorpService = new PaycorpService();
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
            if ($existing) {
                return response()->json([
                    'status' => 'already_registered',
                    'message' => 'This membership has already been registered for the Conference.',
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
                    'email' => $member->personal_email ?? ($member->official_email ?? ''),
                    'mobile' => $member->personal_mobilenumber ?? ($member->official_mobilenumber ?? ''),
                ],
                'discount_eligible' => true,
                'discount_percentage' => 99.5,
                'message' => 'SLIA Member - Eligible for special rate of LKR 50'
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
     * Handle Conference registration with payment initiation
     */
    public function initiatePayment(Request $request)
    {
        Log::info('Conference Registration attempt started', $request->all());
        
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string|max:50',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'nic_passport' => 'required_if:is_slia_member,false|string|max:50', // Required for non-members
            'category' => 'required|in:slia_member,general_public,international',
            'is_slia_member' => 'required|boolean', // New field to indicate membership status
            'include_lunch' => 'required|boolean',
            'meal_preference' => 'required_if:include_lunch,true|nullable|in:veg,non_veg',
            'concession_eligible' => 'sometimes|boolean',
            'concession_applied' => 'sometimes|boolean',
            'total_amount' => 'required|numeric|min:0',
            'test_mode' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            Log::error('Conference Validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Please check your input data.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $membership_number = trim(strtoupper($data['membership_number']));

        Log::info('Processing Conference registration for: ' . $membership_number);

        $existing = ConferenceRegistration::where('membership_number', $membership_number)->first();
        if ($existing) {
            Log::warning('Conference Registration blocked - already exists: ' . $membership_number);
            return response()->json([
                'success' => false,
                'message' => 'This membership has already been registered for the Conference.',
                'registered_date' => $existing->created_at->format('F j, Y, g:i a'),
                'existing_email' => $existing->email,
                'attended' => $existing->attended,
                'food_received' => $existing->food_received
            ], 409);
        }

        // Check if member verification is required for SLIA members
        if ($data['category'] === 'slia_member') {
            $member = DB::table('member_details')
                ->where('membership_no', $membership_number)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'SLIA membership verification failed. Please verify your membership first.'
                ], 400);
            }
        }

        DB::beginTransaction();
        
        try {
            Log::info('Creating Conference registration record...');
            
            $registration = ConferenceRegistration::create([
                'membership_number' => $membership_number,
                'full_name' => trim($data['full_name']),
                'email' => trim($data['email']),
                'phone' => trim($data['mobile']),
                'category' => $data['category'],
                'is_slia_member' => $data['is_slia_member'],
                'member_verified' => $data['category'] === 'slia_member',
                'nic_passport' => $data['nic_passport'] ?? null,
                'include_lunch' => $data['include_lunch'],
                'meal_preference' => $data['meal_preference'] ?? null,
                'food_received' => false,
                'attended' => false,
                'concession_eligible' => $data['concession_eligible'] ?? false,
                'concession_applied' => $data['concession_applied'] ?? false,
                'payment_status' => 'pending',
                'total_amount' => $data['total_amount'] // Store the calculated total
            ]);

            Log::info('Conference Registration created with ID: ' . $registration->id);

            // Use production mode only - no test mode
            $paymentAmount = $data['total_amount']; // Use the calculated amount from frontend

            // Real payment mode - integrate with Paycorp
            $paycorpData = [
                'amount' => $paymentAmount,
                'registration_id' => $registration->id,
                'membership_number' => $registration->membership_number,
                'full_name' => $registration->full_name,
                'email' => $registration->email,
                'client_ref' => 'CONF' . $registration->id,
                'comment' => 'SLIA Conference Registration - ' . $registration->full_name
            ];

            $paymentInit = $this->paycorpService->initPayment($paycorpData);

            if (!$paymentInit['success']) {
                throw new \Exception('Payment initialization failed: ' . ($paymentInit['message'] ?? 'Unknown error'));
            }

            // Store payment request ID in registration
            $registration->update([
                'payment_reqid' => $paymentInit['reqid'],
                'payment_status' => 'initiated'
            ]);

            Log::info('Paycorp payment initialized for registration ID: ' . $registration->id, [
                'reqid' => $paymentInit['reqid']
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration created successfully. Please proceed to payment.',
                'registration_id' => $registration->id,
                'payment_page_url' => $paymentInit['paymentPageUrl'],
                'payment_reqid' => $paymentInit['reqid'],
                'payment_amount' => $paymentAmount,
                'payment_currency' => 'LKR'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Conference Registration Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'membership' => $membership_number ?? 'unknown',
                'data' => $data ?? []
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conference Registration could not be completed. Please try again.',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // In routes/api.php

// In your controller
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
                $registration->update([
                    'payment_status' => 'completed'
                ]);

                Log::info('Payment notification processed successfully for reqid: ' . $reqid);
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
            $membership_number = trim(strtoupper($data['membership_number']));

            $registration = ConferenceRegistration::where('membership_number', $membership_number)->first();

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

            Log::info('Conference Attendance marked for: ' . $membership_number, [
                'member_name' => $registration->full_name,
                'food_received' => $registration->food_received
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance marked successfully.' . 
                    (isset($data['mark_food_received']) && $data['mark_food_received'] && $registration->include_lunch ? 
                     ' Food marked as received.' : ''),
                'data' => [
                    'membership_number' => $registration->membership_number,
                    'full_name' => $registration->full_name,
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
            $membership_number = trim(strtoupper($data['membership_number']));

            $registration = ConferenceRegistration::where('membership_number', $membership_number)->first();

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

            Log::info('Conference Food marked as received for: ' . $membership_number, [
                'member_name' => $registration->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Food marked as received successfully.',
                'data' => [
                    'membership_number' => $registration->membership_number,
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
            $registration = ConferenceRegistration::where('membership_number', $membership_number)
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
            
            // Send email with PDF attachment
            $pdfContent = $pdf->output();
            Mail::to($registration->email)
                ->send(new ConferenceRegistrationMail($registration, $pdfContent));

            Log::info('Conference Email resent successfully to: ' . $registration->email);

            return response()->json([
                'success' => true,
                'message' => 'Conference attendance pass has been resent to your email.'
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

            $registration = ConferenceRegistration::where('membership_number', $membership_number)
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
            $total = ConferenceRegistration::count();
            $attended = ConferenceRegistration::where('attended', true)->count();
            $notAttended = ConferenceRegistration::where('attended', false)->count();
            $sliaMembers = ConferenceRegistration::where('category', 'slia_member')->count();
            $generalPublic = ConferenceRegistration::where('category', 'general_public')->count();
            $international = ConferenceRegistration::where('category', 'international')->count();
            $today = ConferenceRegistration::whereDate('created_at', today())->count();
            $lastWeek = ConferenceRegistration::whereDate('created_at', '>=', now()->subDays(7))->count();
            $foodReceived = ConferenceRegistration::where('food_received', true)->count();
            $withLunch = ConferenceRegistration::where('include_lunch', true)->count();
            $paid = ConferenceRegistration::where('payment_status', 'completed')->count();
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
                    'slia_members' => $sliaMembers,
                    'general_public' => $generalPublic,
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
                'include_lunch', 'meal_preference', 'food_received', 'attended', 
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
            $registrations = ConferenceRegistration::all();

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
                    'Registration Time'
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
                        $registration->created_at->format('H:i:s')
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

            $registration->update($validator->validated());

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

            $membership_number = $registration->membership_number;
            $registration->delete();

            Log::info('Conference Registration deleted for ID: ' . $id, [
                'membership_number' => $membership_number,
                'deleted_by' => auth()->user()->id ?? 'system'
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
     * HELPER METHODS
     */

    /**
     * Generate QR content
     */
    private function generateQrContent($registration)
    {
        return json_encode([
            'membership' => $registration->membership_number,
            'id' => $registration->id,
            'type' => 'conference_registration'
        ]);
    }

    /**
     * Generate QR code
     */
    private function generateQrCode($content)
    {
        return QrCode::size(150)
            ->color(0, 51, 102)
            ->backgroundColor(255, 255, 255)
            ->generate($content);
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

        $pdf = Pdf::loadView('emails.conference-registration', $data);
        
        return $pdf;
    }

    /**
     * Send conference email
     */
    private function sendConferenceEmail($registration, $pdf, $resend = false)
    {
        try {
            // Generate PDF content
            $pdfContent = $pdf->output();
            
            // Send email
            Mail::to($registration->email)
                ->send(new ConferenceRegistrationMail($registration, $pdfContent));

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
 * Payment callback from Paycorp with HMAC verification
 */
public function paymentCallback(Request $request)
{
    Log::info('Paycorp Payment callback received', $request->all());
    
    try {
        $reqid = $request->input('reqid');
        $status = $request->input('status');
        $hmacSignature = $request->header('X-HMAC-Signature');
        
        if (!$reqid) {
            throw new \Exception('Payment request ID not found in callback');
        }
        
        // Verify HMAC signature if provided
        if ($hmacSignature) {
            $payload = $request->all();
            if (!$this->paycorpService->verifyHmacSignature($payload, $hmacSignature)) {
                Log::error('HMAC signature verification failed', [
                    'reqid' => $reqid,
                    'received_signature' => $hmacSignature
                ]);
                throw new \Exception('Invalid HMAC signature');
            }
        }
        
        // Find registration by payment_reqid
        $registration = ConferenceRegistration::where('payment_reqid', $reqid)->first();
        
        if (!$registration) {
            // Try alternative: check in extraData from request
            $extraData = $request->input('extraData');
            if ($extraData) {
                $extraArray = json_decode($extraData, true);
                $registrationId = $extraArray['registration_id'] ?? null;
                
                if ($registrationId) {
                    $registration = ConferenceRegistration::find($registrationId);
                }
            }
        }
        
        if (!$registration) {
            throw new \Exception('Registration not found for reqid: ' . $reqid);
        }
        
        // Complete payment with Paycorp
        $paymentComplete = $this->paycorpService->completePayment($reqid);
        
        if ($paymentComplete['success']) {
            // Payment successful
            $paymentRefNo = $paymentComplete['data']['txnReference'] ?? 'PC-' . $reqid;
            
            $registration->update([
                'payment_ref_no' => $paymentRefNo,
                'payment_status' => 'completed',
                'payment_response' => json_encode($paymentComplete['data'])
            ]);
            
            // Generate QR code and send email
            $qrContent = $this->generateQrContent($registration);
            $qrCode = $this->generateQrCode($qrContent);
            $pdf = $this->generateConferencePass($registration, $qrCode);
            $this->sendConferenceEmail($registration, $pdf);
            
            Log::info('Payment successful for registration ID: ' . $registration->id, [
                'payment_ref' => $paymentRefNo,
                'reqid' => $reqid
            ]);
            
            // Redirect to success page
            $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
            return redirect()->away($frontendUrl . '/conference/confirmation/' . $registration->id);
            
        } else {
            // Payment failed
            Log::warning('Payment failed for registration ID: ' . $registration->id, [
                'reqid' => $reqid,
                'error' => $paymentComplete['message']
            ]);
            
            // Update registration with failure status
            $registration->update([
                'payment_status' => 'failed',
                'payment_response' => json_encode($paymentComplete)
            ]);
            
            // Redirect to failure page
            $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
            return redirect()->away($frontendUrl . '/conference/payment-failed?registration_id=' . $registration->id);
        }
        
    } catch (\Exception $e) {
        Log::error('Payment callback error: ' . $e->getMessage());
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
        return redirect()->away($frontendUrl . '/register/conference?error=payment_callback_failed');
    }
}
}