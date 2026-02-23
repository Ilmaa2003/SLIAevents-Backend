<?php

namespace App\Http\Controllers;

use App\Models\AGMRegistration;
// ... (omitting some imports for brevity as this is for easy access/reference)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AGMRegistrationController extends Controller
{
    /**
     * Verify membership number for AGM
     */
    public function verifyAndCheckMember($membership_number)
    {
        try {
            $membership_number = trim(strtoupper($membership_number));
            
            if (empty($membership_number)) {
                return response()->json([
                    'status' => 'invalid_member',
                    'message' => 'Membership number is required.'
                ], 400);
            }

            $existing = AGMRegistration::where('membership_number', $membership_number)->first();
            if ($existing) {
                return response()->json([
                    'status' => 'already_registered',
                    'message' => 'This membership has already been registered for the AGM.',
                    'registered_at' => $existing->created_at->format('Y-m-d H:i:s'),
                    'existing_email' => $existing->email,
                    'attended' => $existing->attended,
                    'meal_received' => $existing->meal_received
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
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AGM Verification error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify membership for AGM. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle AGM registration
     */
    public function store(Request $request)
    {
        Log::info('AGM Registration attempt started', $request->all());
        
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string|max:50',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'meal_preference' => 'required|in:veg,non_veg',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please check your input data.',
                'errors' => $validator->errors()
            ], 422);
        }

        // ... rest of store method ...
    }
}
