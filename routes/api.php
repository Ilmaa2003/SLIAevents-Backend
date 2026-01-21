<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InaugurationRegistrationController;
use App\Http\Controllers\AGMRegistrationController;
use App\Http\Controllers\ExhibitionRegistrationController;
use App\Http\Controllers\ConferenceRegistrationController;
use App\Http\Controllers\AdminController;

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
            [InaugurationRegistrationController::class, 'destroy']
        )->where('id', '[0-9]+');
        
        // Generate QR code
        Route::get('/registrations/{id}/qr-code', 
            [InaugurationRegistrationController::class, 'getQrCode']
        )->where('id', '[0-9]+');
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
    });
});

/* ================= CONFERENCE ROUTES ================= */
Route::prefix('conference')->group(function () {
    
    // Health check
    Route::get('/', function() {
        return response()->json([
            'status' => 'active',
            'service' => 'SLIA National Conference Registration API',
            'version' => '1.0.0',
            'features' => ['payment_gateway', 'attendance_tracking', 'qr_codes']
        ]);
    });
    
    // Verify member
    Route::get('/verify-member/{membership_number}', 
        [ConferenceRegistrationController::class, 'verifyMember']
    );
    
    // Register with payment
    Route::post('/registrations', 
        [ConferenceRegistrationController::class, 'initiatePayment']
    );
    
    // Check payment status
    Route::get('/check-payment/{registrationId}', 
        [ConferenceRegistrationController::class, 'checkPaymentStatus']
    );
    
    // Resend email
    Route::post('/resend-email', 
        [ConferenceRegistrationController::class, 'resendEmail']
    );
    
    // Get registration by ID
    Route::get('/registrations/{id}', 
        [ConferenceRegistrationController::class, 'getRegistration']
    )->where('id', '[0-9]+');
    
    // Payment callbacks
    Route::post('/payment/callback', 
        [ConferenceRegistrationController::class, 'paymentCallback']
    );
    
    Route::post('/payment/notify', 
        [ConferenceRegistrationController::class, 'paymentNotify']
    );
    
    // Stats
    Route::get('/stats', 
        [ConferenceRegistrationController::class, 'getStats']
    );
    
    // Admin routes
    Route::prefix('admin')->group(function () {
        // Get all registrations
        Route::get('/registrations', 
            [ConferenceRegistrationController::class, 'getAllRegistrations']
        );
        
        // Export to CSV
        Route::get('/export', 
            [ConferenceRegistrationController::class, 'exportRegistrations']
        );
        
        // Get single registration
        Route::get('/registrations/{id}', 
            [ConferenceRegistrationController::class, 'getRegistration']
        )->where('id', '[0-9]+');
        
        // Update registration
        Route::put('/registrations/{id}', 
            [ConferenceRegistrationController::class, 'updateRegistration']
        )->where('id', '[0-9]+');
        
        // Delete registration
        Route::delete('/registrations/{id}', 
            [ConferenceRegistrationController::class, 'deleteRegistration']
        )->where('id', '[0-9]+');
        
        // Generate QR code for registration
        Route::get('/registrations/{id}/qr-code', 
            [ConferenceRegistrationController::class, 'generateQrCode']
        )->where('id', '[0-9]+');
        
        // Mark attendance
        Route::post('/registrations/{id}/mark-attendance', 
            [ConferenceRegistrationController::class, 'markAttendance']
        )->where('id', '[0-9]+');
        
        // Mark meal received
        Route::post('/registrations/{id}/mark-meal', 
            [ConferenceRegistrationController::class, 'markMealReceived']
        )->where('id', '[0-9]+');
        
        // Get payment statistics
        Route::get('/payment-stats', 
            [ConferenceRegistrationController::class, 'getPaymentStats']
        );
    });
});

/* ================= ADMIN ROUTES ================= */
Route::prefix('admin')->group(function () {
    // Login
    Route::post('/login', [AdminController::class, 'login']);
    
    // Check admins
    Route::get('/list', [AdminController::class, 'checkAdmins']);
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
                'features' => ['registration', 'attendance_tracking', 'meal_tracking', 'qr_codes']
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
                'features' => ['registration', 'attendance_tracking', 'meal_tracking', 'qr_codes']
            ],
            [
                'name' => 'conference',
                'endpoint' => '/api/conference',
                'description' => 'National Conference Registration',
                'features' => ['payment_gateway', 'registration', 'attendance_tracking', 'qr_codes', 'email_notifications']
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
            'get_stats' => 'GET /{event}/stats',
            'admin_list' => 'GET /{event}/admin/registrations',
            'admin_export' => 'GET /{event}/admin/export'
        ],
        'maintenance_mode' => app()->isDownForMaintenance()
    ]);
});