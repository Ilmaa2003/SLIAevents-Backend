<?php

namespace App\Http\Controllers;

use App\Models\AGMRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

use App\Jobs\SendAGMPassEmail;

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
            Log::error('AGM Validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Please check your input data.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $membership_number = trim(strtoupper($data['membership_number']));

        Log::info('Processing AGM registration for: ' . $membership_number);

        $existing = AGMRegistration::where('membership_number', $membership_number)->first();
        if ($existing) {
            Log::warning('AGM Registration blocked - already exists: ' . $membership_number);
            return response()->json([
                'success' => false,
                'message' => 'This membership has already been registered for the AGM.',
                'registered_date' => $existing->created_at->format('F j, Y, g:i a'),
                'existing_email' => $existing->email,
                'attended' => $existing->attended,
                'meal_received' => $existing->meal_received
            ], 409);
        }

        DB::beginTransaction();
        
        try {
            Log::info('Creating AGM registration record...');
            
            $registration = AGMRegistration::create([
                'membership_number' => $membership_number,
                'full_name' => trim($data['full_name']),
                'email' => trim($data['email']),
                'mobile' => trim($data['mobile']),
                'meal_preference' => $data['meal_preference'],
                'attended' => false,
                'meal_received' => false,
            ]);

            Log::info('AGM Registration created with ID: ' . $registration->id);

            $qrContent = json_encode([
                'membership' => $membership_number,
                'id' => $registration->id,
                'type' => 'agm_registration'
            ]);
            
            Log::info('Generating AGM QR code...');
            
            $qrSvg = QrCode::format('svg')
                ->size(400)
                ->margin(2)
                ->color(30, 58, 138)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->generate($qrContent);
            
            $qrCode = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
            
            Log::info('AGM QR code generated successfully');

            Log::info('AGM QR code generated successfully');

            // Dispatch Email Job (Async)
            try {
                SendAGMPassEmail::dispatch($data, $qrCode, $registration->id);
                $emailSent = true;
                Log::info('AGM Email Job dispatched for: ' . $data['email']);
            } catch (\Exception $e) {
                Log::error('Failed to dispatch AGM email job: ' . $e->getMessage());
                $emailSent = false;
            }

            DB::commit();
            
            Log::info('AGM Registration completed successfully for: ' . $membership_number);

            return response()->json([
                'success' => true,
                'message' => $emailSent 
                    ? 'AGM Registration successful! Your attendance pass has been sent to your email.' 
                    : 'AGM Registration successful! However, email delivery failed. Please use the download option to get your pass.',
                'email_sent' => $emailSent,
                'registration_id' => $registration->id,
                'membership_number' => $registration->membership_number,
                'qr_code' => $qrCode
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('AGM Registration Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'membership' => $membership_number ?? 'unknown',
                'data' => $data ?? []
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AGM Registration could not be completed. Please try again.',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Mark attendance for AGM
     */
    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string',
            'mark_meal_received' => 'boolean',
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

            $registration = AGMRegistration::where('membership_number', $membership_number)->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            if ($registration->attended) {
                $message = 'Attendance already marked for this member.';
                if (isset($data['mark_meal_received']) && $data['mark_meal_received'] && !$registration->meal_received) {
                    $registration->update(['meal_received' => true]);
                    $message .= ' Meal received status updated.';
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'meal_received' => $registration->meal_received
                ], 200);
            }

            $updateData = [
                'attended' => true,
            ];

            if (isset($data['mark_meal_received']) && $data['mark_meal_received']) {
                $updateData['meal_received'] = true;
            }

            $registration->update($updateData);

            Log::info('AGM Attendance marked for: ' . $membership_number, [
                'member_name' => $registration->full_name,
                'meal_received' => $registration->meal_received
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance marked successfully.' . 
                    (isset($data['mark_meal_received']) && $data['mark_meal_received'] ? ' Meal marked as received.' : ''),
                'data' => [
                    'membership_number' => $registration->membership_number,
                    'full_name' => $registration->full_name,
                    'attended' => $registration->attended,
                    'meal_received' => $registration->meal_received,
                    'meal_preference' => $registration->meal_preference
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AGM Attendance marking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to mark attendance.'
            ], 500);
        }
    }

    /**
     * Mark meal as received for AGM
     */
    public function markMealReceived(Request $request)
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

            $registration = AGMRegistration::where('membership_number', $membership_number)->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            if (!$registration->attended) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member has not checked in for attendance yet.'
                ], 400);
            }

            if ($registration->meal_received) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meal already marked as received.'
                ], 400);
            }

            if (!$registration->meal_preference) {
                return response()->json([
                    'success' => false,
                    'message' => 'No meal preference selected.'
                ], 400);
            }

            $registration->update(['meal_received' => true]);

            Log::info('AGM Meal marked as received for: ' . $membership_number, [
                'member_name' => $registration->full_name,
                'meal_preference' => $registration->meal_preference
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meal marked as received successfully.',
                'data' => [
                    'membership_number' => $registration->membership_number,
                    'full_name' => $registration->full_name,
                    'meal_preference' => $registration->meal_preference,
                    'meal_received' => $registration->meal_received
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AGM Meal marking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to mark meal as received.'
            ], 500);
        }
    }

    /**
     * Resend AGM email
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
            $registration = AGMRegistration::where('membership_number', $membership_number)
                ->where('email', $data['email'])
                ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            Log::info('Resending AGM email for registration ID: ' . $registration->id);

            $qrContent = json_encode([
                'id' => $registration->id,
                'membership' => $membership_number,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'meal' => $registration->meal_preference,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received,
                'timestamp' => now()->timestamp,
                'event' => 'Annual General Meeting'
            ]);
            
            $qrSvg = QrCode::format('svg')
                ->size(400)
                ->margin(2)
                ->color(30, 58, 138)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->generate($qrContent);
            
            $qrCode = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

            $pdf = Pdf::loadView('pdf.agm-pass', [
                'membership' => $membership_number,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'mobile' => $registration->mobile,
                'meal_preference' => $registration->meal_preference,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received,
                'qr' => $qrCode,
                'date' => $registration->created_at->format('F j, Y'),
                'time' => $registration->created_at->format('h:i A'),
                'registration_id' => $registration->id,
                'event_name' => 'Annual General Meeting',
                'event_date' => 'December 15, 2024',
                'event_time' => '10:00 AM - 2:00 PM',
                'venue' => 'Main Auditorium, Conference Center'
            ]);

            $pdfContent = $pdf->output();

            Mail::send('emails.agm-pass-resend', [
                'name' => $registration->full_name,
                'membership' => $membership_number,
                'meal_preference' => $registration->meal_preference,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received,
                'registration_date' => $registration->created_at->format('F j, Y'),
                'event_date' => 'December 15, 2024',
                'event_time' => '10:00 AM - 2:00 PM',
                'venue' => 'Main Auditorium, Conference Center'
            ], function ($message) use ($registration, $pdfContent) {
                $message->to($registration->email)
                        ->subject('AGM Attendance Pass (Resent)');

                if ($ccEmail = env('MAIL_ALWAYS_CC')) {
                    $message->cc($ccEmail);
                }

                $message->attachData($pdfContent, 
                            'AGM-Registration-Pass-' . $registration->membership_number . '.pdf'
                        );
            });

            Log::info('AGM Email resent successfully to: ' . $registration->email);

            return response()->json([
                'success' => true,
                'message' => 'AGM attendance pass has been resent to your email.'
            ]);

        } catch (\Exception $e) {
            Log::error('AGM Resend email failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to resend AGM email. Please try again.'
            ], 500);
        }
    }

    /**
     * Generate PDF for AGM download
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

            $registration = AGMRegistration::where('membership_number', $membership_number)
                ->where('email', $data['email'])
                ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            Log::info('Manual AGM PDF download for: ' . $membership_number);

            $pdf = Pdf::loadView('pdf.agm-pass', [
                'membership' => $membership_number,
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $registration->mobile,
                'meal_preference' => $registration->meal_preference,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received,
                'qr' => $data['qr'],
                'date' => $registration->created_at->format('F j, Y'),
                'time' => $registration->created_at->format('h:i A'),
                'registration_id' => $registration->id,
                'event_name' => 'Annual General Meeting',
                'event_date' => 'December 15, 2024',
                'event_time' => '10:00 AM - 2:00 PM',
                'venue' => 'Main Auditorium, Conference Center'
            ]);

            return $pdf->download('AGM-Registration-Pass-' . $membership_number . '.pdf');

        } catch (\Exception $e) {
            Log::error('AGM PDF generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate AGM PDF.'
            ], 500);
        }
    }

    /**
     * Get AGM registration statistics
     */
    public function getStats()
    {
        try {
            $total = AGMRegistration::count();
            $attended = AGMRegistration::where('attended', true)->count();
            $notAttended = AGMRegistration::where('attended', false)->count();
            $veg = AGMRegistration::where('meal_preference', 'veg')->count();
            $nonVeg = AGMRegistration::where('meal_preference', 'non_veg')->count();
            $today = AGMRegistration::whereDate('created_at', today())->count();
            $lastWeek = AGMRegistration::whereDate('created_at', '>=', now()->subDays(7))->count();
            $mealReceived = AGMRegistration::where('meal_received', true)->count();
            
            $attendanceRate = $total > 0 ? ($attended / $total) * 100 : 0;
            $mealRate = $attended > 0 ? ($mealReceived / $attended) * 100 : 0;

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_registrations' => $total,
                    'attended' => $attended,
                    'not_attended' => $notAttended,
                    'vegetarian' => $veg,
                    'non_vegetarian' => $nonVeg,
                    'attendance_rate' => round($attendanceRate, 2) . '%',
                    'meal_received' => $mealReceived,
                    'meal_distribution_rate' => round($mealRate, 2) . '%',
                    'registered_today' => $today,
                    'registered_last_7_days' => $lastWeek,
                    'last_registration' => AGMRegistration::latest()->first()->created_at ?? null,
                    'last_attendance' => AGMRegistration::where('attended', true)
                        ->latest('updated_at')
                        ->first()->updated_at ?? null,
                    'last_meal_served' => AGMRegistration::where('meal_received', true)
                        ->latest('updated_at')
                        ->first()->updated_at ?? null
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('AGM Stats retrieval failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch AGM stats'], 500);
        }
    }

    /**
     * Get all AGM registrations (admin function)
     */
    public function getAllRegistrations(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $attended = $request->get('attended');
            $mealPreference = $request->get('meal_preference');
            $mealReceived = $request->get('meal_received');
            $search = $request->get('search');

            $query = DB::table('agm_registrations')->select([
                'id', 'membership_number', 'full_name', 'email', 'mobile', 
                'attended', 'meal_received', 'meal_preference',
                'created_at', 'updated_at'
            ]);

            if ($attended !== null) {
                $query->where('attended', filter_var($attended, FILTER_VALIDATE_BOOLEAN));
            }

            if ($mealPreference) {
                $query->where('meal_preference', $mealPreference);
            }

            if ($mealReceived !== null) {
                $query->where('meal_received', filter_var($mealReceived, FILTER_VALIDATE_BOOLEAN));
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('membership_number', 'like', "%{$search}%")
                      ->orWhere('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('mobile', 'like', "%{$search}%");
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
            Log::error('Get AGM registrations failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch registrations'], 500);
        }
    }

    /**
     * Export AGM registrations to CSV
     */
    public function exportRegistrations(Request $request)
    {
        try {
            $registrations = AGMRegistration::all();

            $filename = 'agm-registrations-' . date('Y-m-d-H-i-s') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($registrations) {
                $file = fopen('php://output', 'w');
                
                fputcsv($file, [
                    'Membership Number',
                    'Full Name',
                    'Email',
                    'Mobile',
                    'Meal Preference',
                    'Attended',
                    'Meal Received',
                    'Registration Date',
                    'Registration Time'
                ]);

                foreach ($registrations as $registration) {
                    fputcsv($file, [
                        $registration->membership_number,
                        $registration->full_name,
                        $registration->email,
                        $registration->mobile,
                        $registration->meal_preference ?: 'N/A',
                        $registration->attended ? 'Yes' : 'No',
                        $registration->meal_received ? 'Yes' : 'No',
                        $registration->created_at->format('Y-m-d'),
                        $registration->created_at->format('H:i:s')
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('AGM Export failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to export data'], 500);
        }
    }

    /**
     * Get AGM registration by ID
     */
    public function getRegistration($id)
    {
        try {
            $registration = AGMRegistration::find($id);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $registration
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get AGM registration failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch registration'], 500);
        }
    }

    /**
     * Update AGM registration
     */
    public function updateRegistration(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'mobile' => 'sometimes|string|max:20',
            'meal_preference' => 'sometimes|in:veg,non_veg',
            'attended' => 'sometimes|boolean',
            'meal_received' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $registration = AGMRegistration::find($id);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            $registration->update($validator->validated());

            Log::info('AGM Registration updated for ID: ' . $id, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'AGM Registration updated successfully.',
                'data' => $registration
            ], 200);

        } catch (\Exception $e) {
            Log::error('Update AGM registration failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to update registration'], 500);
        }
    }

    /**
     * Delete AGM registration
     */
    public function deleteRegistration($id)
    {
        try {
            $registration = AGMRegistration::find($id);

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            $identifier = $registration->membership_number ?? $registration->email ?? 'N/A';
            $registration->delete();

            Log::warning('AGM Registration deleted', [
                'identifier' => $identifier,
                'registration_id' => $id,
                'deleted_by' => auth()->id() ?? 'admin'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'AGM Registration deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Delete AGM registration failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to delete registration'], 500);
        }
    }

    /**
     * Generate QR code for AGM registration
     */
    public function generateQrCode($id)
    {
        try {
            $registration = AGMRegistration::find($id);
            
            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'AGM Registration not found.'
                ], 404);
            }

            $qrContent = json_encode([
                'id' => $registration->id,
                'membership' => $registration->membership_number,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'meal' => $registration->meal_preference,
                'timestamp' => now()->timestamp,
                'event' => 'Annual General Meeting',
                'event_date' => '2024',
                'type' => 'agm_registration',
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received
            ]);
            
            $qrSvg = QrCode::format('svg')
                ->size(400)
                ->margin(2)
                ->color(30, 58, 138)
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
                    'meal_preference' => $registration->meal_preference,
                    'attended' => $registration->attended,
                    'meal_received' => $registration->meal_received
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AGM QR code generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate QR code.'
            ], 500);
        }
    }

    /**
     * Bulk mark attendance for AGM
     */
    public function bulkMarkAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'membership_numbers' => 'required|array',
            'membership_numbers.*' => 'string',
            'mark_meal_received' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input.'
            ], 422);
        }

        try {
            $data = $validator->validated();
            $membership_numbers = array_map('strtoupper', $data['membership_numbers']);
            $markMealReceived = $data['mark_meal_received'] ?? false;

            $results = [
                'successful' => [],
                'failed' => [],
                'already_attended' => []
            ];

            foreach ($membership_numbers as $membership_number) {
                $membership_number = trim($membership_number);
                $registration = AGMRegistration::where('membership_number', $membership_number)->first();

                if (!$registration) {
                    $results['failed'][] = [
                        'membership_number' => $membership_number,
                        'reason' => 'Registration not found'
                    ];
                    continue;
                }

                if ($registration->attended) {
                    $results['already_attended'][] = [
                        'membership_number' => $membership_number,
                        'name' => $registration->full_name,
                        'previously_attended' => $registration->updated_at
                    ];
                    continue;
                }

                $updateData = [
                    'attended' => true,
                ];

                if ($markMealReceived) {
                    $updateData['meal_received'] = true;
                }

                $registration->update($updateData);

                $results['successful'][] = [
                    'membership_number' => $membership_number,
                    'name' => $registration->full_name,
                    'meal_received' => $registration->meal_received
                ];
            }

            Log::info('AGM Bulk attendance marked', [
                'total' => count($membership_numbers),
                'successful' => count($results['successful']),
                'already_attended' => count($results['already_attended']),
                'failed' => count($results['failed'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk attendance marking completed.',
                'results' => $results,
                'summary' => [
                    'total' => count($membership_numbers),
                    'successful' => count($results['successful']),
                    'already_attended' => count($results['already_attended']),
                    'failed' => count($results['failed'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AGM Bulk attendance marking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to process bulk attendance.'
            ], 500);
        }
    }
}