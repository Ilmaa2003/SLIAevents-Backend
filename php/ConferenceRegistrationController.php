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
use App\Jobs\SendConferencePassEmail;

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
                'discount_percentage' => 50, // LKR 10,000 -> LKR 5,000
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
     * Handle Conference registration with payment initiation
     */
    public function initiatePayment(Request $request)
    {
        Log::info('Conference Registration attempt started', $request->all());
        
        $validator = Validator::make($request->all(), [
            'membership_number' => 'nullable|string|max:50',
            'student_id' => 'required_if:category,student|nullable|string|max:50',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'nic_passport' => 'nullable|string|max:50',
            'category' => 'required|in:slia_member,student,general_public,international,test_user',
            'include_lunch' => 'required|boolean',
            'meal_preference' => 'required_if:include_lunch,true|nullable|in:veg,non_veg',
            'registration_fee' => 'required|numeric|min:0',
            'lunch_fee' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
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
        
        // Determine unique identifier
        $uniqueIdentifier = $data['category'] === 'student' 
            ? trim(strtoupper($data['student_id'] ?? ''))
            : trim(strtoupper($data['membership_number'] ?? ''));

        Log::info('Processing Conference registration for: ' . $uniqueIdentifier . ' (Category: ' . $data['category'] . ')');

        // ... rest of implementation ...
    }
}
