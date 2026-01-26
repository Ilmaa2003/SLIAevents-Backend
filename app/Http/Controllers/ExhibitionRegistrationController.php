<?php

namespace App\Http\Controllers;

use App\Models\ExhibitionRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ExhibitionRegistrationController extends Controller
{
    /**
     * Verify membership number
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

            $existing = ExhibitionRegistration::where('membership_number', $membership_number)->first();
            if ($existing) {
                return response()->json([
                    'status' => 'already_registered',
                    'message' => 'This membership has already been registered for the exhibition.',
                    'registered_at' => $existing->created_at->format('Y-m-d H:i:s'),
                    'existing_email' => $existing->email
                ]);
            }

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
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exhibition Verification error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify membership. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle exhibition registration
     */
    public function store(Request $request)
    {
        Log::info('Exhibition registration attempt started', $request->all());
        
        $validator = Validator::make($request->all(), [
            'membership_number' => 'required|string|max:50',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'meal_preference' => 'required|in:veg,non_veg',
        ]);

        if ($validator->fails()) {
            Log::error('Exhibition validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Please check your input data.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $membership_number = trim(strtoupper($data['membership_number']));

        Log::info('Processing exhibition registration for: ' . $membership_number);

        $existing = ExhibitionRegistration::where('membership_number', $membership_number)->first();
        if ($existing) {
            Log::warning('Exhibition registration blocked - already exists: ' . $membership_number);
            return response()->json([
                'success' => false,
                'message' => 'This membership has already been registered for the exhibition.',
                'registered_date' => $existing->created_at->format('F j, Y, g:i a'),
                'existing_email' => $existing->email
            ], 409);
        }

        DB::beginTransaction();
        
        try {
            Log::info('Creating exhibition registration record...');
            
            $registration = ExhibitionRegistration::create([
                'membership_number' => $membership_number,
                'full_name' => trim($data['full_name']),
                'email' => trim($data['email']),
                'mobile' => trim($data['mobile']),
                'meal_preference' => $data['meal_preference'],
                'attended' => false,
                'meal_received' => false,
            ]);

            Log::info('Exhibition registration created with ID: ' . $registration->id);

            $qrContent = json_encode([
                'membership' => $membership_number,
                'id' => $registration->id,
                'type' => 'exhibition_registration'
            ]);
            
            $qrSvg = QrCode::format('svg')
                ->size(400)
                ->margin(2)
                ->color(124, 58, 237)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->generate($qrContent);
            
            $qrCode = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
            
            $pdf = Pdf::loadView('pdf.exhibition-pass', [
                'membership' => $membership_number,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'qr' => $qrCode,
                'date' => now()->format('F j, Y'),
                'time' => now()->format('h:i A'),
                'registration_id' => $registration->id,
                'pass_type' => 'Exhibition Entry Pass',
                'event_name' => 'SLIA Annual Exhibition 2026',
                'attended' => false,
                'meal_received' => false
            ]);

            $pdfContent = $pdf->output();

            $emailSent = false;
            Log::info('Attempting to send exhibition email to: ' . $data['email']);
            
            try {
                Mail::send('emails.exhibition-pass', [
                    'name' => $data['full_name'],
                    'membership' => $membership_number,
                    'email' => $data['email'],
                    'date' => now()->format('F j, Y'),
                    'time' => now()->format('h:i A'),
                    'event_name' => 'SLIA Annual Exhibition 2026'
                ], function ($message) use ($data, $pdfContent) {
                    $message->to($data['email'])
                            ->subject('SLIA Exhibition - Your Entry Pass & Registration Confirmation')
                            ->attachData($pdfContent, 
                                'SLIA-Exhibition-Pass-' . $data['membership_number'] . '.pdf',
                                ['mime' => 'application/pdf']
                            );
                });
                
                $emailSent = true;
                Log::info('Exhibition email sent successfully to: ' . $data['email']);
                
            } catch (\Exception $e) {
                Log::error('Exhibition email sending failed: ' . $e->getMessage(), ['email' => $data['email']]);
                $emailSent = false;
            }

            DB::commit();
            
            Log::info('Exhibition registration completed successfully for: ' . $membership_number);

            return response()->json([
                'success' => true,
                'message' => $emailSent 
                    ? 'Exhibition registration successful! Your entry pass has been sent to your email.' 
                    : 'Exhibition registration successful! However, email delivery failed. Please use the download option to get your pass.',
                'email_sent' => $emailSent,
                'registration_id' => $registration->id,
                'membership_number' => $registration->membership_number,
                'qr_code' => $qrCode
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Exhibition Registration Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'membership' => $membership_number ?? 'unknown',
                'data' => $data ?? []
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Exhibition registration could not be completed. Please try again.',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Mark attendance for exhibition
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

            $registration = ExhibitionRegistration::where('membership_number', $membership_number)->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
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

            Log::info('Exhibition Attendance marked for: ' . $membership_number, [
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
                    'meal_received' => $registration->meal_received
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exhibition Attendance marking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to mark attendance.'
            ], 500);
        }
    }

    /**
     * Mark meal as received
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

            $registration = ExhibitionRegistration::where('membership_number', $membership_number)->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
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

            $registration->update(['meal_received' => true]);

            Log::info('Exhibition Meal marked as received for: ' . $membership_number, [
                'member_name' => $registration->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Meal marked as received successfully.',
                'data' => [
                    'membership_number' => $registration->membership_number,
                    'full_name' => $registration->full_name,
                    'meal_received' => $registration->meal_received
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exhibition Meal marking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to mark meal as received.'
            ], 500);
        }
    }

    /**
     * Resend exhibition email
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
            $registration = ExhibitionRegistration::where('membership_number', $membership_number)
                ->where('email', $data['email'])
                ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
                ], 404);
            }

            Log::info('Resending exhibition email for registration ID: ' . $registration->id);

            $qrContent = json_encode([
                'id' => $registration->id,
                'membership' => $membership_number,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'timestamp' => now()->timestamp,
                'event' => 'SLIA Annual Exhibition 2026',
                'pass_type' => 'exhibition_only',
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received
            ]);
            
            $qrSvg = QrCode::format('svg')
                ->size(400)
                ->margin(2)
                ->color(124, 58, 237)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->generate($qrContent);
            
            $qrCode = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

            $pdf = Pdf::loadView('pdf.exhibition-pass', [
                'membership' => $membership_number,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'mobile' => $registration->mobile,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received,
                'qr' => $qrCode,
                'date' => $registration->created_at->format('F j, Y'),
                'time' => $registration->created_at->format('h:i A'),
                'registration_id' => $registration->id,
                'pass_type' => 'Exhibition Entry Pass',
                'event_name' => 'SLIA Annual Exhibition 2026'
            ]);

            $pdfContent = $pdf->output();

            Mail::send('emails.exhibition-pass-resend', [
                'name' => $registration->full_name,
                'membership' => $membership_number,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received,
                'date' => now()->format('F j, Y'),
                'time' => now()->format('h:i A'),
                'event_name' => 'SLIA Annual Exhibition 2026'
            ], function ($message) use ($registration, $pdfContent) {
                $message->to($registration->email)
                        ->subject('SLIA Exhibition Entry Pass (Resent)')
                        ->attachData($pdfContent, 
                            'SLIA-Exhibition-Pass-' . $registration->membership_number . '.pdf'
                        );
            });

            Log::info('Exhibition email resent successfully to: ' . $registration->email);

            return response()->json([
                'success' => true,
                'message' => 'Exhibition entry pass has been resent to your email.'
            ]);

        } catch (\Exception $e) {
            Log::error('Exhibition resend email failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to resend exhibition email. Please try again.'
            ], 500);
        }
    }

    /**
     * Generate exhibition PDF for download
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

            $registration = ExhibitionRegistration::where('membership_number', $membership_number)
                ->where('email', $data['email'])
                ->first();

            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
                ], 404);
            }

            Log::info('Manual exhibition PDF download for: ' . $membership_number);

            $pdf = Pdf::loadView('pdf.exhibition-pass', [
                'membership' => $membership_number,
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $registration->mobile,
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received,
                'qr' => $data['qr'],
                'date' => $registration->created_at->format('F j, Y'),
                'time' => $registration->created_at->format('h:i A'),
                'registration_id' => $registration->id,
                'pass_type' => 'Exhibition Entry Pass',
                'event_name' => 'SLIA Annual Exhibition 2026'
            ]);

            return $pdf->download('SLIA-Exhibition-Pass-' . $membership_number . '.pdf');

        } catch (\Exception $e) {
            Log::error('Exhibition PDF generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate exhibition PDF.'
            ], 500);
        }
    }

    /**
     * Get exhibition registration statistics
     */
    public function getStats()
    {
        try {
            $total = ExhibitionRegistration::count();
            $today = ExhibitionRegistration::whereDate('created_at', today())->count();
            $lastWeek = ExhibitionRegistration::whereDate('created_at', '>=', now()->subDays(7))->count();
            $attended = ExhibitionRegistration::where('attended', true)->count();
            $notAttended = ExhibitionRegistration::where('attended', false)->count();
            $mealReceived = ExhibitionRegistration::where('meal_received', true)->count();
            
            $vegCount = ExhibitionRegistration::where('meal_preference', 'veg')->count();
            $nonVegCount = ExhibitionRegistration::where('meal_preference', 'non_veg')->count();
            
            $attendanceRate = $total > 0 ? round(($attended / $total) * 100, 2) : 0;
            $mealRate = $attended > 0 ? round(($mealReceived / $attended) * 100, 2) : 0;

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
                    'veg_meals' => $vegCount,
                    'non_veg_meals' => $nonVegCount,
                    'meal_distribution_rate' => $mealRate . '%',
                    'last_registration' => ExhibitionRegistration::latest()->first()->created_at ?? null,
                    'last_attendance' => ExhibitionRegistration::where('attended', true)
                        ->latest('updated_at')
                        ->first()->updated_at ?? null
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Exhibition stats retrieval failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch exhibition stats'], 500);
        }
    }

    /**
     * Admin: Get all exhibition registrations
     */
    public function getAllRegistrations(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $attended = $request->get('attended');
            $mealReceived = $request->get('meal_received');
            $search = $request->get('search');

            $query = DB::table('exhibition_registrations')->select([
                'id', 'membership_number', 'full_name', 'email', 'mobile', 
                'attended', 'meal_received', 'meal_preference',
                'created_at', 'updated_at'
            ]);

            if ($attended !== null) {
                $query->where('attended', filter_var($attended, FILTER_VALIDATE_BOOLEAN));
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
            Log::error('Get exhibition registrations failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to fetch registrations'], 500);
        }
    }

    /**
     * Export exhibition registrations to CSV
     */
    public function exportRegistrations(Request $request)
    {
        try {
            $registrations = ExhibitionRegistration::all();

            $filename = 'exhibition-registrations-' . date('Y-m-d-H-i-s') . '.csv';
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
            Log::error('Exhibition export failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to export data'], 500);
        }
    }

    /**
     * Get a single exhibition registration
     */
    public function getRegistration($id)
    {
        try {
            $registration = ExhibitionRegistration::find($id);
            
            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $registration
            ]);

        } catch (\Exception $e) {
            Log::error('Get exhibition registration failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch exhibition registration.'
            ], 500);
        }
    }

    /**
     * Update a exhibition registration
     */
    public function updateRegistration(Request $request, $id)
    {
        try {
            $registration = ExhibitionRegistration::find($id);
            
            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'full_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'mobile' => 'sometimes|string|max:20',
                'attended' => 'sometimes|boolean',
                'meal_received' => 'sometimes|boolean',
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

            Log::info('Exhibition registration updated', [
                'id' => $registration->id,
                'membership_number' => $registration->membership_number,
                'updates' => $data
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Exhibition registration updated successfully.',
                'data' => $registration
            ]);

        } catch (\Exception $e) {
            Log::error('Exhibition update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to update exhibition registration.'
            ], 500);
        }
    }

    /**
     * Delete a exhibition registration
     */
    public function deleteRegistration($id)
    {
        try {
            $registration = ExhibitionRegistration::find($id);
            
            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
                ], 404);
            }

            Log::info('Deleting exhibition registration', [
                'id' => $registration->id,
                'membership_number' => $registration->membership_number,
                'name' => $registration->full_name
            ]);

            $registration->delete();

            return response()->json([
                'success' => true,
                'message' => 'Exhibition registration deleted successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete exhibition registration failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete exhibition registration.'
            ], 500);
        }
    }

    /**
     * Generate QR code for a exhibition registration
     */
    public function generateQrCode($id)
    {
        try {
            $registration = ExhibitionRegistration::find($id);
            
            if (!$registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exhibition registration not found.'
                ], 404);
            }

            $qrContent = json_encode([
                'id' => $registration->id,
                'membership' => $registration->membership_number,
                'name' => $registration->full_name,
                'email' => $registration->email,
                'timestamp' => now()->timestamp,
                'event' => 'SLIA Annual Exhibition 2026',
                'pass_type' => 'exhibition_only',
                'attended' => $registration->attended,
                'meal_received' => $registration->meal_received
            ]);
            
            $qrSvg = QrCode::format('svg')
                ->size(400)
                ->margin(2)
                ->color(124, 58, 237)
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
                    'attended' => $registration->attended,
                    'meal_received' => $registration->meal_received
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exhibition QR code generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate QR code.'
            ], 500);
        }
    }
}