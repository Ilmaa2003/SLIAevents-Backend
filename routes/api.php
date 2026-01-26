<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InaugurationRegistrationController;
use App\Http\Controllers\AGMRegistrationController;
use App\Http\Controllers\ExhibitionRegistrationController;
use App\Http\Controllers\ConferenceRegistrationController;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Admin Auth and Global Stats
Route::post('/admin/login', [AdminController::class, 'login']);
Route::get('/admin/global-stats', [AdminController::class, 'getGlobalStats']);
Route::get('/admin/lookup-registration', [AdminController::class, 'lookupRegistration']);

/* ================= INAUGURATION ROUTES ================= */
Route::prefix('inauguration')->group(function () {
    
    // Health check
    Route::get('/', function() {
        return response()->json([
            'status' => 'active',
            'service' => 'SLIA Inauguration Registration API',
            'version' => '2.0.0',
            'features' => ['attendance_tracking', 'meal_tracking']
        ]);
    });
    
    // Verify member
    Route::get('/verify-member/{membership_number}', 
        [InaugurationRegistrationController::class, 'verifyAndCheckMember']
    );
    
    // Register
    Route::post('/registrations', 
        [InaugurationRegistrationController::class, 'store']
    );
    
    // Mark attendance
    Route::post('/mark-attendance', 
        [InaugurationRegistrationController::class, 'markAttendance']
    );
    
    // Mark meal as received
    Route::post('/mark-meal-received', 
        [InaugurationRegistrationController::class, 'markMealReceived']
    );
    
    // Bulk mark attendance
    Route::post('/bulk-mark-attendance', 
        [InaugurationRegistrationController::class, 'bulkMarkAttendance']
    );
    
    // Resend email
    Route::post('/resend-email', 
        [InaugurationRegistrationController::class, 'resendEmail']
    );
    
    // Download PDF
    Route::post('/generate-a4-pass', 
        [InaugurationRegistrationController::class, 'generateA4Pass']
    );
    
    // Stats
    Route::get('/stats', 
        [InaugurationRegistrationController::class, 'getStats']
    );
    
    // Admin routes
    Route::prefix('admin')->group(function () {
        // Get all registrations
        Route::get('/registrations', 
            [InaugurationRegistrationController::class, 'getAllRegistrations']
        );
        
        // Export to CSV
        Route::get('/export', 
            [InaugurationRegistrationController::class, 'exportRegistrations']
        );
        
        // Get single registration
        Route::get('/registrations/{id}', 
            [InaugurationRegistrationController::class, 'getRegistration']
        )->where('id', '[0-9]+');
        
        // Update registration
        Route::put('/registrations/{id}', 
            [InaugurationRegistrationController::class, 'updateRegistration']
        )->where('id', '[0-9]+');
        
        // Delete registration
        Route::delete('/registrations/{id}', 
            [InaugurationRegistrationController::class, 'deleteRegistration'] // FIXED
        )->where('id', '[0-9]+');
        
        // Generate QR code
        Route::get('/registrations/{id}/qr-code', 
            [InaugurationRegistrationController::class, 'generateQrCode'] // FIXED
        )->where('id', '[0-9]+');
        
        // Bulk mark attendance
        Route::post('/bulk-attendance', 
            [InaugurationRegistrationController::class, 'bulkMarkAttendance']
        );
    });
});

/* ================= AGM ROUTES ================= */
Route::prefix('agm')->group(function () {
    
    // Health check
    Route::get('/', function() {
        return response()->json([
            'status' => 'active',
            'service' => 'SLIA AGM Registration API',
            'version' => '2.0.0',
            'features' => ['attendance_tracking', 'meal_tracking']
        ]);
    });
    
    // Verify member
    Route::get('/verify-member/{membership_number}', 
        [AGMRegistrationController::class, 'verifyAndCheckMember']
    );
    
    // Register
    Route::post('/registrations', 
        [AGMRegistrationController::class, 'store']
    );
    
    // Mark attendance
    Route::post('/mark-attendance', 
        [AGMRegistrationController::class, 'markAttendance']
    );
    
    // Mark meal as received
    Route::post('/mark-meal-received', 
        [AGMRegistrationController::class, 'markMealReceived']
    );
    
    // Bulk mark attendance
    Route::post('/bulk-mark-attendance', 
        [AGMRegistrationController::class, 'bulkMarkAttendance']
    );
    
    // Resend email
    Route::post('/resend-email', 
        [AGMRegistrationController::class, 'resendEmail']
    );
    
    // Download PDF
    Route::post('/generate-a4-pass', 
        [AGMRegistrationController::class, 'generateA4Pass']
    );
    
    // Stats
    Route::get('/stats', 
        [AGMRegistrationController::class, 'getStats']
    );
    
    // Admin routes
    Route::prefix('admin')->group(function () {
        // Get all registrations
        Route::get('/registrations', 
            [AGMRegistrationController::class, 'getAllRegistrations']
        );
        
        // Export to CSV
        Route::get('/export', 
            [AGMRegistrationController::class, 'exportRegistrations']
        );
        
        // Get single registration
        Route::get('/registrations/{id}', 
            [AGMRegistrationController::class, 'getRegistration']
        )->where('id', '[0-9]+');
        
        // Update registration
        Route::put('/registrations/{id}', 
            [AGMRegistrationController::class, 'updateRegistration']
        )->where('id', '[0-9]+');
        
        // Delete registration
        Route::delete('/registrations/{id}', 
            [AGMRegistrationController::class, 'deleteRegistration']
        )->where('id', '[0-9]+');
        
        // Generate QR code
        Route::get('/registrations/{id}/qr-code', 
            [AGMRegistrationController::class, 'generateQrCode']
        )->where('id', '[0-9]+');
        
        // Bulk mark attendance
        Route::post('/bulk-attendance', 
            [AGMRegistrationController::class, 'bulkMarkAttendance']
        );
    });
});

/* ================= EXHIBITION ROUTES ================= */
Route::prefix('exhibition')->group(function () {
    
    // Health check
    Route::get('/', function() {
        return response()->json([
            'status' => 'active',
            'service' => 'SLIA Exhibition Registration API',
            'version' => '2.0.0',
            'features' => ['attendance_tracking', 'meal_tracking']
        ]);
    });
    
    // Verify member
    Route::get('/verify-member/{membership_number}', 
        [ExhibitionRegistrationController::class, 'verifyAndCheckMember']
    );
    
    // Register
    Route::post('/registrations', 
        [ExhibitionRegistrationController::class, 'store']
    );
    
    // Mark attendance
    Route::post('/mark-attendance', 
        [ExhibitionRegistrationController::class, 'markAttendance']
    );
    
    // Mark meal as received
    Route::post('/mark-meal-received', 
        [ExhibitionRegistrationController::class, 'markMealReceived']
    );
    
    // Bulk mark attendance
    Route::post('/bulk-mark-attendance', 
        [ExhibitionRegistrationController::class, 'bulkMarkAttendance']
    );
    
    // Resend email
    Route::post('/resend-email', 
        [ExhibitionRegistrationController::class, 'resendEmail']
    );
    
    // Download PDF
    Route::post('/generate-a4-pass', 
        [ExhibitionRegistrationController::class, 'generateA4Pass']
    );
    
    // Stats
    Route::get('/stats', 
        [ExhibitionRegistrationController::class, 'getStats']
    );
    
    // Admin routes
    Route::prefix('admin')->group(function () {
        // Get all registrations
        Route::get('/registrations', 
            [ExhibitionRegistrationController::class, 'getAllRegistrations']
        );
        
        // Export to CSV
        Route::get('/export', 
            [ExhibitionRegistrationController::class, 'exportRegistrations']
        );
        
        // Get single registration
        Route::get('/registrations/{id}', 
            [ExhibitionRegistrationController::class, 'getRegistration']
        )->where('id', '[0-9]+');
        
        // Update registration
        Route::put('/registrations/{id}', 
            [ExhibitionRegistrationController::class, 'updateRegistration']
        )->where('id', '[0-9]+');
        
        // Delete registration
        Route::delete('/registrations/{id}', 
            [ExhibitionRegistrationController::class, 'deleteRegistration']
        )->where('id', '[0-9]+');
        
        // Generate QR code
        Route::get('/registrations/{id}/qr-code', 
            [ExhibitionRegistrationController::class, 'generateQrCode']
        )->where('id', '[0-9]+');
        
        // Bulk mark attendance
        Route::post('/bulk-attendance', 
            [ExhibitionRegistrationController::class, 'bulkMarkAttendance']
        );
    });
});

Route::get('/test-paycorp', [ConferenceRegistrationController::class, 'testPaycorpConnection']);

// Conference Registration Routes
Route::prefix('conference')->group(function () {
    // Membership verification
    Route::get('/verify-member/{membership_number}', [ConferenceRegistrationController::class, 'verifyMember']);
    
    // Registration & payment initiation
    Route::post('/initiate-payment', [ConferenceRegistrationController::class, 'initiatePayment']);
    
    // Payment callback (from Paycorp)
    Route::post('/payment-callback', [ConferenceRegistrationController::class, 'paymentCallback']);
    Route::get('/payment-callback', [ConferenceRegistrationController::class, 'paymentCallback']);
    
    // Payment status checking
    Route::get('/check-payment/{id}', [ConferenceRegistrationController::class, 'checkPaymentStatus']);
    
    // Payment notification (optional)
    Route::post('/payment-notify', [ConferenceRegistrationController::class, 'paymentNotify']);
    
    // Attendance and food marking
    Route::post('/mark-attendance', [ConferenceRegistrationController::class, 'markAttendance']);
    Route::post('/mark-food-received', [ConferenceRegistrationController::class, 'markFoodReceived']);
    
    // Email resend
    Route::post('/resend-email', [ConferenceRegistrationController::class, 'resendEmail']);
    
    // Statistics
    Route::get('/stats', [ConferenceRegistrationController::class, 'getStats']);
    Route::get('/payment-stats', [ConferenceRegistrationController::class, 'getPaymentStats']);
    
    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/registrations', [ConferenceRegistrationController::class, 'getAllRegistrations']);
        Route::get('/export-registrations', [ConferenceRegistrationController::class, 'exportRegistrations']);
        Route::get('/registration/{id}', [ConferenceRegistrationController::class, 'getRegistration']);
        Route::put('/registration/{id}', [ConferenceRegistrationController::class, 'updateRegistration']);
        Route::delete('/registration/{id}', [ConferenceRegistrationController::class, 'deleteRegistration']);
        Route::get('/payment-stats', [ConferenceRegistrationController::class, 'getPaymentStats']);
        Route::get('/dashboard-summary', [ConferenceRegistrationController::class, 'dashboardSummary']);
    });
    
    // Test routes
    Route::get('/test-payment', [ConferenceRegistrationController::class, 'testPaymentConnection']);
    Route::post('/simulate-payment/{id}', [ConferenceRegistrationController::class, 'simulatePayment']);
    
    // ============ ADDITIONAL ROUTES ============
    
    // 1. Dashboard Summary
    Route::get('/dashboard-summary', [ConferenceRegistrationController::class, 'dashboardSummary']);
    
    // 2. Search registrations
    Route::get('/search', [ConferenceRegistrationController::class, 'searchRegistrations']);
    
    // 3. Bulk operations
    Route::post('/bulk-mark-attendance', [ConferenceRegistrationController::class, 'bulkMarkAttendance']);
    Route::post('/bulk-mark-food', [ConferenceRegistrationController::class, 'bulkMarkFoodReceived']);
    
    // 4. Export to different formats
    Route::get('/export-excel', [ConferenceRegistrationController::class, 'exportToExcel']);
    Route::get('/export-pdf', [ConferenceRegistrationController::class, 'exportToPDF']);
    
    // 5. Registration by membership number
    Route::get('/by-membership/{membership_number}', [ConferenceRegistrationController::class, 'getByMembershipNumber']);
    Route::get('/by-email/{email}', [ConferenceRegistrationController::class, 'getByEmail']);
    
    // 6. Check-in/Check-out operations
    Route::post('/check-in/{id}', [ConferenceRegistrationController::class, 'checkIn']);
    Route::post('/check-out/{id}', [ConferenceRegistrationController::class, 'checkOut']);
    Route::get('/check-in-history/{id}', [ConferenceRegistrationController::class, 'checkInHistory']);
    
    // 7. QR Code operations
    Route::get('/qr-code/{id}', [ConferenceRegistrationController::class, 'generateQrCode']);
    Route::post('/validate-qr', [ConferenceRegistrationController::class, 'validateQrCode']);
    
    // 8. Certificate generation
    Route::get('/certificate/{id}', [ConferenceRegistrationController::class, 'generateCertificate']);
    Route::post('/send-certificate/{id}', [ConferenceRegistrationController::class, 'sendCertificate']);
    
    // 9. Payment retry/refund operations
    Route::post('/retry-payment/{id}', [ConferenceRegistrationController::class, 'retryPayment']);
    Route::post('/refund-payment/{id}', [ConferenceRegistrationController::class, 'refundPayment']);
    Route::get('/payment-history/{id}', [ConferenceRegistrationController::class, 'paymentHistory']);
    
    // 10. Reports generation
    Route::get('/report/daily', [ConferenceRegistrationController::class, 'dailyReport']);
    Route::get('/report/category', [ConferenceRegistrationController::class, 'categoryReport']);
    Route::get('/report/attendance', [ConferenceRegistrationController::class, 'attendanceReport']);
    Route::get('/report/payment', [ConferenceRegistrationController::class, 'paymentReport']);
    
    // 11. Settings/Configuration
    Route::get('/settings', [ConferenceRegistrationController::class, 'getSettings']);
    Route::put('/settings', [ConferenceRegistrationController::class, 'updateSettings']);
    
    // 12. Notification operations
    Route::post('/send-notification', [ConferenceRegistrationController::class, 'sendNotification']);
    Route::get('/notifications', [ConferenceRegistrationController::class, 'getNotifications']);
    
    // 13. Analytics
    Route::get('/analytics/trends', [ConferenceRegistrationController::class, 'registrationTrends']);
    Route::get('/analytics/peak-hours', [ConferenceRegistrationController::class, 'peakHours']);
    Route::get('/analytics/geographic', [ConferenceRegistrationController::class, 'geographicAnalytics']);
    
    // 14. Backup/Restore
    Route::post('/backup', [ConferenceRegistrationController::class, 'backupData']);
    Route::post('/restore', [ConferenceRegistrationController::class, 'restoreData']);
    
    // 15. Health check
    Route::get('/health', [ConferenceRegistrationController::class, 'healthCheck']);
    
    // 16. Fee calculation
    Route::post('/calculate-fee', [ConferenceRegistrationController::class, 'calculateFee']);
    
    // 17. Session management
    Route::get('/sessions', [ConferenceRegistrationController::class, 'getSessions']);
    Route::post('/register-session/{id}', [ConferenceRegistrationController::class, 'registerForSession']);
    
    // 18. Guest management
    Route::post('/add-guest/{id}', [ConferenceRegistrationController::class, 'addGuest']);
    Route::delete('/remove-guest/{guest_id}', [ConferenceRegistrationController::class, 'removeGuest']);
    
    // 19. Food preference management
    Route::post('/update-food-preference/{id}', [ConferenceRegistrationController::class, 'updateFoodPreference']);
    Route::get('/food-preferences', [ConferenceRegistrationController::class, 'getFoodPreferences']);
    
    // 20. Feedback/Survey
    Route::post('/submit-feedback/{id}', [ConferenceRegistrationController::class, 'submitFeedback']);
    Route::get('/feedback/{id}', [ConferenceRegistrationController::class, 'getFeedback']);
    Route::get('/feedback-summary', [ConferenceRegistrationController::class, 'feedbackSummary']);
    
    // 21. Photo/Media upload
    Route::post('/upload-photo/{id}', [ConferenceRegistrationController::class, 'uploadPhoto']);
    Route::get('/photos/{id}', [ConferenceRegistrationController::class, 'getPhotos']);
    
    // 22. Conference schedule
    Route::get('/schedule', [ConferenceRegistrationController::class, 'getSchedule']);
    Route::post('/update-schedule/{id}', [ConferenceRegistrationController::class, 'updateScheduleAttendance']);
    
    // 23. Accreditation/CPD points
    Route::post('/accredit/{id}', [ConferenceRegistrationController::class, 'accreditParticipant']);
    Route::get('/cpd-points/{id}', [ConferenceRegistrationController::class, 'getCpdPoints']);
    
    // 24. Mass email/SMS
    Route::post('/mass-communication', [ConferenceRegistrationController::class, 'massCommunication']);
    
    // 25. Integration endpoints
    Route::post('/sync-to-crm', [ConferenceRegistrationController::class, 'syncToCRM']);
    Route::get('/integration-status', [ConferenceRegistrationController::class, 'integrationStatus']);
});

/* ================= ADMIN ROUTES ================= */
Route::prefix('admin')->group(function () {
    // Login
    Route::post('/login', [AdminController::class, 'login']);
    
    // Check admins
    Route::get('/list', [AdminController::class, 'checkAdmins']);
    
    // Logout
    Route::post('/logout', [AdminController::class, 'logout']);
    
    // Validate token
    Route::get('/validate', [AdminController::class, 'validateToken']);
});

/* ================= COMBINED EVENT ROUTES ================= */
Route::prefix('events')->group(function () {
    
    // Combined stats
    Route::get('/all-stats', function() {
        // This would typically call all four controllers' getStats methods
        // For now, return a placeholder
        return response()->json([
            'message' => 'Use individual event endpoints: /inauguration/stats, /agm/stats, /exhibition/stats, /conference/stats'
        ]);
    });
    
    // Quick check member across all events
    Route::get('/check-member/{membership_number}', function($membership_number) {
        return response()->json([
            'message' => 'Use individual event verify-member endpoints for detailed information'
        ]);
    });
});

/* ================= API HEALTH CHECK ================= */
Route::get('/health', function() {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toDateTimeString(),
        'services' => [
            'inauguration' => 'active',
            'agm' => 'active',
            'exhibition' => 'active',
            'conference' => 'active'
        ],
        'features' => [
            'attendance_tracking' => 'enabled',
            'meal_tracking' => 'enabled',
            'qr_codes' => 'enabled',
            'email_notifications' => 'enabled',
            'pdf_generation' => 'enabled',
            'payment_gateway' => 'enabled'
        ]
    ]);
});

/* ================= API INFO ================= */
Route::get('/', function() {
    return response()->json([
        'app' => 'SLIA Event Registration System',
        'version' => '3.0.0',
        'description' => 'API for SLIA Event Registrations with Payment Gateway Integration',
        'available_services' => [
            [
                'name' => 'inauguration',
                'endpoint' => '/api/inauguration',
                'description' => 'Inauguration Event Registration',
                'features' => ['registration', 'attendance_tracking', 'meal_tracking', 'qr_codes', 'bulk_operations']
            ],
            [
                'name' => 'agm',
                'endpoint' => '/api/agm',
                'description' => 'Annual General Meeting Registration',
                'features' => ['registration', 'attendance_tracking', 'meal_tracking', 'qr_codes', 'bulk_operations']
            ],
            [
                'name' => 'exhibition',
                'endpoint' => '/api/exhibition',
                'description' => 'Exhibition Event Registration',
                'features' => ['registration', 'attendance_tracking', 'meal_tracking', 'qr_codes', 'bulk_operations']
            ],
            [
                'name' => 'conference',
                'endpoint' => '/api/conference',
                'description' => 'National Conference Registration',
                'features' => ['payment_gateway', 'registration', 'attendance_tracking', 'qr_codes', 'email_notifications', 'bulk_operations']
            ]
        ],
        'new_features' => [
            'payment_gateway' => 'Sampath Bank integration for conference payments',
            'attendance_tracking' => 'Track member attendance at events',
            'meal_tracking' => 'Track meal distribution to attendees',
            'bulk_operations' => 'Bulk attendance marking',
            'enhanced_stats' => 'Detailed statistics with payment tracking'
        ],
        'endpoints' => [
            'registration' => 'POST /{event}/registrations',
            'verify_member' => 'GET /{event}/verify-member/{membership_number}',
            'mark_attendance' => 'POST /{event}/mark-attendance',
            'mark_meal_received' => 'POST /{event}/mark-meal-received',
            'bulk_attendance' => 'POST /{event}/bulk-mark-attendance',
            'get_stats' => 'GET /{event}/stats',
            'admin_list' => 'GET /{event}/admin/registrations',
            'admin_export' => 'GET /{event}/admin/export'
        ],
        'maintenance_mode' => app()->isDownForMaintenance()
    ]);
});