<?php
// File: FellowshipRegistrationController.php

namespace App\Http\Controllers;

use App\Models\FellowshipRegistration;
use App\Services\SampathPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Mail\FellowshipRegistrationMail;
use App\Jobs\SendFellowshipPassEmail;
use App\Mail\ManualEntryNotificationMail;
use Illuminate\Support\Facades\Mail;

class FellowshipRegistrationController extends Controller
{
    protected $fellowshipPaymentService;

    public function __construct()
    {
        $this->fellowshipPaymentService = new \App\Services\FellowshipPaymentService();
    }

    /**
     * Verify membership number for Members Night 2026
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

            $existing = FellowshipRegistration::where('membership_number', $membership_number)->first();
            
            if ($existing && $existing->payment_status === 'completed') {
                return response()->json([
                    'status' => 'already_registered',
                    'message' => 'This membership has already been registered and paid for the Members Night 2026.',
                    'registered_at' => $existing->created_at->format('Y-m-d H:i:s'),
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
                'payable' => 3500,
                'message' => 'SLIA Member - Special rate of LKR 3,500'
            ]);

        } catch (\Exception $e) {
            Log::error('Fellowship Verification error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify membership. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle Fellowship registration with payment initiation
     */
    public function initiatePayment(Request $request)
    {
        Log::info('Fellowship Registration attempt started', $request->all());
        
        $validator = Validator::make($request->all(), [
            'membership_number' => 'nullable|string',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'nic_passport' => 'nullable|string|max:50',
            'category' => 'required|in:slia_member,general_public,international,test_user',
            'registration_fee' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please check your input data.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $membershipNumber = !empty($data['membership_number']) ? trim(strtoupper($data['membership_number'])) : null;
        $nicPassport = !empty($data['nic_passport']) ? trim(strtoupper($data['nic_passport'])) : null;

        // Check for existing registration
        $existingQuery = FellowshipRegistration::query();
        if (($data['category'] === 'slia_member') && $membershipNumber) {
            $existingQuery->where('membership_number', $membershipNumber);
        } else if ($nicPassport) {
            $existingQuery->where('nic_passport', $nicPassport);
        } else {
            $existingQuery->where('email', $data['email']);
        }
        
        $existing = $existingQuery->first();
        
        if ($existing && $existing->payment_status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'You have already registered and paid for the Members Night 2026.',
                'status' => 'already_registered',
                'registration_id' => $existing->id
            ], 200);
        }

        // Manual entry notification logic
        if (($data['category'] === 'slia_member') && $membershipNumber) {
            try {
                $member = DB::table('member_details')->where('membership_no', $membershipNumber)->first();
                if (!$member) {
                    $adminEmail = env('MAIL_ALWAYS_CC', 'sliaoffice2@gmail.com');
                    Mail::to($adminEmail)->send(new ManualEntryNotificationMail(
                        'Members Night 2026',
                        $membershipNumber,
                        $data['full_name'],
                        $data['email'],
                        $data['mobile'],
                        'N/A'
                    ));
                    Log::info('Manual entry notification sent for unverified member', ['membership' => $membershipNumber]);
                }
            } catch (\Exception $e) {
                Log::error('Members Night manual entry notify failed: ' . $e->getMessage());
            }
        }

        DB::beginTransaction();
        try {
            if ($existing) {
                $registration = $existing;
                $registration->update([
                    'membership_number' => $membershipNumber,
                    'full_name' => trim($data['full_name']),
                    'email' => trim($data['email']),
                    'phone' => trim($data['mobile']),
                    'category' => $data['category'],
                    'nic_passport' => $nicPassport,
                    'registration_fee' => $data['registration_fee'],
                    'total_amount' => $data['total_amount'],
                    'payment_status' => 'pending', 
                    'payment_reqid' => null
                ]);
            } else {
                $registration = FellowshipRegistration::create([
                    'membership_number' => $membershipNumber,
                    'full_name' => trim($data['full_name']),
                    'email' => trim($data['email']),
                    'phone' => trim($data['mobile']),
                    'category' => $data['category'],
                    'nic_passport' => $nicPassport,
                    'attended' => false,
                    'payment_status' => 'pending',
                    'registration_fee' => $data['registration_fee'],
                    'total_amount' => $data['total_amount']
                ]);
            }

            $paymentData = [
                'full_name' => $registration->full_name,
                'email' => $registration->email,
                'amount' => $data['total_amount'],
                'client_ref' => 'FELL-' . $registration->id,
                'registration_id' => $registration->id,
                'event_type' => 'fellowship' // Add event type for callback routing
            ];

            $paymentInit = $this->fellowshipPaymentService->initiatePayment($paymentData);

            if (!$paymentInit['success']) {
                throw new \Exception('Payment initialization failed: ' . ($paymentInit['message'] ?? 'Unknown error'));
            }

            $registration->update([
                'payment_reqid' => $paymentInit['transaction_id'] ?? null,
                'payment_status' => 'initiated'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration created successfully. Redirecting to payment gateway...',
                'registration_id' => $registration->id,
                'payment_url' => $paymentInit['payment_url'],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fellowship Registration Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration could not be completed. Please try again.',
            ], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus($registrationId)
    {
        $registration = FellowshipRegistration::find($registrationId);
        if (!$registration) {
            return response()->json(['success' => false, 'message' => 'Registration not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'payment_status' => $registration->payment_status,
            'payment_ref_no' => $registration->payment_ref_no,
            'registration_id' => $registration->id,
        ]);
    }

    /**
     * Mark attendance for Members Night 2026
     */
    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid input.'], 422);
        }

        try {
            $identifier = trim(strtoupper($request->membership_number));
            $registration = FellowshipRegistration::where(function($q) use ($identifier) {
                $q->where('membership_number', $identifier)
                  ->orWhere('nic_passport', $identifier)
                  ->orWhere('id', $identifier);
            })->first();

            if (!$registration) {
                return response()->json(['success' => false, 'message' => 'Registration not found.'], 404);
            }

            if ($registration->payment_status !== 'completed') {
                return response()->json(['success' => false, 'message' => 'Payment not completed.'], 400);
            }

            if ($registration->attended) {
                return response()->json(['success' => true, 'message' => 'Attendance already marked.'], 200);
            }

            $registration->update([
                'attended' => true,
                'check_in_time' => now(),
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Attendance marked successfully.',
                'data' => $registration
            ]);

        } catch (\Exception $e) {
            Log::error('Fellowship Attendance marking failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to mark attendance.'], 500);
        }
    }

    /**
     * Get all registrations (Admin)
     */
    public function getAllRegistrations()
    {
        try {
            $perPage = request()->get('per_page', 100);
            $query = FellowshipRegistration::query();
            
            if (request()->has('payment_status')) {
                $query->where('payment_status', request()->get('payment_status'));
            }
            
            $registrations = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $registrations
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch registrations'], 500);
        }
    }

    public function getStats()
    {
        // For consistent stats, we should probably focus on completed registrations for the main count
        // or keep total as all attempts. Let's align with Conference which uses 'completed' for 'total' in some contexts
        // or explicit 'total' vs 'paid'.
        
        $total = FellowshipRegistration::count();
        $completed = FellowshipRegistration::where('payment_status', 'completed')->count();
        $pending = FellowshipRegistration::where('payment_status', 'pending')->count();
        $initiated = FellowshipRegistration::where('payment_status', 'initiated')->count();
        $failed = FellowshipRegistration::where('payment_status', 'failed')->count();
        $attended = FellowshipRegistration::where('attended', 1)->count();
        $revenue = FellowshipRegistration::where('payment_status', 'completed')->sum('total_amount');

        return response()->json([
            'success' => true,
            'stats' => [
                'total_registrations' => $completed, // Changing this to show COMPLETED registrations as the main number 
                'total_attempts' => $total,
                'payment_completed' => $completed,
                'payment_pending' => $pending + $initiated,
                'payment_failed' => $failed,
                'attended' => $attended,
                'total_amount' => $revenue,
                'not_attended' => $completed - $attended,
                'attendance_rate' => $completed > 0 ? round(($attended / $completed) * 100, 1) . '%' : '0%',
                'registered_today' => FellowshipRegistration::whereDate('created_at', now()->toDateString())->count(),
                'registered_last_7_days' => FellowshipRegistration::where('created_at', '>=', now()->subDays(7))->count(),
                'last_registration' => FellowshipRegistration::latest()->first()?->created_at?->toDateTimeString(),
                'last_attendance' => FellowshipRegistration::where('attended', 1)->latest('check_in_time')->first()?->check_in_time?->toDateTimeString(),
            ]
        ]);
    }

    /**
     * Export registrations as CSV
     */
    public function export()
    {
        try {
            $registrations = FellowshipRegistration::orderBy('created_at', 'desc')->get();
            $filename = "fellowship_registrations_" . date('Y-m-d_H-i-s') . ".csv";

            $headers = [
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            ];

            $columns = [
                'ID', 
                'Membership Number', 
                'Full Name', 
                'Email', 
                'Phone', 
                'Category', 
                'Attended', 
                'Payment Status', 
                'Total Amount', 
                'Registered At'
            ];

            $callback = function() use ($registrations, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                foreach ($registrations as $reg) {
                    fputcsv($file, [
                        $reg->id,
                        $reg->membership_number,
                        $reg->full_name,
                        $reg->email,
                        $reg->phone,
                        $reg->category,
                        $reg->attended ? 'Yes' : 'No',
                        $reg->payment_status,
                        $reg->total_amount,
                        $reg->created_at,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Export failed: ' . $e->getMessage());
            return response()->json(['message' => 'Export failed'], 500);
        }
    }

    /**
     * Get single registration
     */
    public function getRegistration($id)
    {
        $registration = FellowshipRegistration::find($id);
        if (!$registration) {
            return response()->json(['success' => false, 'message' => 'Registration not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $registration]);
    }

    /**
     * Update registration (Admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $registration = FellowshipRegistration::find($id);
            if (!$registration) {
                return response()->json(['success' => false, 'message' => 'Registration not found'], 404);
            }

            $validated = $request->validate([
                'attended' => 'boolean',
                'payment_status' => 'in:pending,initiated,completed,failed',
                'full_name' => 'string|max:255',
                'email' => 'email|max:255',
                'phone' => 'string|max:20',
            ]);

            $registration->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Registration updated successfully',
                'data' => $registration
            ]);
        } catch (\Exception $e) {
            Log::error('Fellowship update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Update failed'], 500);
        }
    }

    /**
     * Update registration
     */
    public function updateRegistration(Request $request, $id)
    {
        $registration = FellowshipRegistration::find($id);
        if (!$registration) {
            return response()->json(['success' => false, 'message' => 'Registration not found'], 404);
        }

        $registration->update($request->all());
        return response()->json(['success' => true, 'message' => 'Registration updated', 'data' => $registration]);
    }

    /**
     * Delete registration
     */
    public function deleteRegistration($id)
    {
        $registration = FellowshipRegistration::find($id);
        if (!$registration) {
            return response()->json(['success' => false, 'message' => 'Registration not found'], 404);
        }

        $registration->delete();
        return response()->json(['success' => true, 'message' => 'Registration deleted']);
    }

    /**
     * Export to CSV
     */
    public function exportRegistrations()
    {
        $registrations = FellowshipRegistration::all();
        $csvHeader = ['ID', 'Membership Number', 'Full Name', 'Email', 'Phone', 'Category', 'NIC/Passport', 'Payment Status', 'Total Amount', 'Attended', 'Check-in Time', 'Created At'];
        
        $handle = fopen('php://output', 'w');
        fputcsv($handle, $csvHeader);

        foreach ($registrations as $reg) {
            fputcsv($handle, [
                $reg->id,
                $reg->membership_number,
                $reg->full_name,
                $reg->email,
                $reg->phone,
                $reg->category,
                $reg->nic_passport,
                $reg->payment_status,
                $reg->total_amount,
                $reg->attended ? 'Yes' : 'No',
                $reg->check_in_time,
                $reg->created_at
            ]);
        }

        fclose($handle);

        return response()->make('', 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fellowship_registrations_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Payment callback from slia.lk
     */
    public function paymentCallback(Request $request)
    {
        Log::info('Fellowship Payment callback received', $request->all());

        try {
            $transactionId = $request->input('transaction_id');
            $reference = $request->input('reference'); // Format: FELL-{registration_id}
            $status = $request->input('status');
            $bankReference = $request->input('bank_reference');
            $signature = $request->input('signature');

            if (!$transactionId || !$reference || !$status) {
                throw new \Exception('Missing required callback parameters');
            }

            // Verify signature
            $callbackData = $request->only(['transaction_id', 'reference', 'status', 'amount', 'bank_reference', 'payment_method', 'response_code', 'response_message', 'timestamp', '_debug_raw']);
            
            if (!$this->fellowshipPaymentService->verifyCallback($callbackData, $signature)) {
                throw new \Exception('Invalid callback signature');
            }

            $registrationId = str_replace('FELL-', '', $reference);
            Log::info('Fellowship Payment Callback: Extracted registration ID', [
                'reference' => $reference,
                'extracted_id' => $registrationId
            ]);
            
            $registration = FellowshipRegistration::find($registrationId);
            
            Log::info('Fellowship Payment Callback: Registration lookup result', [
                'id' => $registrationId,
                'found' => $registration ? 'yes' : 'no',
                'registration_data' => $registration ? $registration->toArray() : null
            ]);

            if (!$registration) {
                Log::error('Payment callback error: Registration not found for reference: ' . $reference);
                throw new \Exception('Registration not found for reference: ' . $reference);
            }

            if ($status === 'completed') {
                $registration->update([
                    'payment_ref_no' => $bankReference ?? $transactionId,
                    'payment_status' => 'completed',
                    'payment_response' => json_encode($callbackData)
                ]);

                // Generate QR and Send Email
                $qrContent = $this->generateQrContent($registration);
                $qrCode = $this->generateQrCode($qrContent);

                $registrationData = [
                    'full_name' => $registration->full_name,
                    'membership_number' => $registration->membership_number,
                    'email' => $registration->email,
                    'mobile' => $registration->phone,
                ];

                SendFellowshipPassEmail::dispatch($registrationData, $qrCode, $registration->id);

                $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
                return redirect()->away($frontendUrl . '/register/fellowship/success?id=' . $registration->id);
            } else {
                $registration->update(['payment_status' => 'failed']);
                $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
                return redirect()->away($frontendUrl . '/register/fellowship/failed?id=' . $registration->id);
            }

        } catch (\Exception $e) {
            Log::error('Fellowship Callback Exception: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function generateQrContent($registration)
    {
        return json_encode([
            'id' => $registration->id,
            'membership' => $registration->membership_number,
            'name' => $registration->full_name,
            'event' => 'fellowship',
            'type' => 'entry_pass'
        ]);
    }

    private function generateQrCode($content)
    {
        return 'data:image/png;base64,' . base64_encode(QrCode::format('png')->size(300)->margin(1)->generate($content));
    }
}
