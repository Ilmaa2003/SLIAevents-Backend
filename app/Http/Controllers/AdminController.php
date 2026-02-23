<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Simple Admin Login - Just match and see
     */
    public function login(Request $request)
    {
        // Get data from request
        $username = trim($request->input('username'));
        $password = trim($request->input('password'));
        
        // Simple validation
        if (empty($username) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username and password are required'
            ]);
        }
        
        // Direct database check - simple string comparison
        $admin = DB::table('admins')
            ->where('username', $username)
            ->where('password', $password)
            ->first();
        
        if ($admin) {
            // Success - just return success message
            return response()->json([
                'success' => true,
                'message' => 'Login successful!',
                'user' => [
                    'username' => $admin->username,
                    'name' => $admin->name
                ]
            ]);
        }
        
        // Failed
        return response()->json([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
    }
    
    /**
     * Check if database has admin data
     */
    public function checkAdmins()
    {
        try {
            $admins = DB::table('admins')->get(['username', 'name', 'email']);
            
            return response()->json([
                'success' => true,
                'count' => $admins->count(),
                'admins' => $admins
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'count' => 0
            ]);
        }
    }
    /**
     * Get statistics for all events in a single call
     * Reduces request overhead for the dashboard
     */
    public function getGlobalStats()
    {
        try {
            $stats = [
                'conference' => [
                    'total_registrations' => DB::table('conference_registrations')->where('payment_status', 'completed')->count(),
                    'attended' => DB::table('conference_registrations')->where('payment_status', 'completed')->where('attended', true)->count(),
                    'total_revenue' => DB::table('conference_registrations')->where('payment_status', 'completed')->sum('total_amount'),
                ],
                'exhibition' => [
                    'total_registrations' => DB::table('exhibition_registrations')->count(),
                    'attended' => DB::table('exhibition_registrations')->where('attended', true)->count(),
                ],
                'agm' => [
                    'total_registrations' => DB::table('agm_registrations')->count(),
                    'attended' => DB::table('agm_registrations')->where('attended', true)->count(),
                ],
                'inauguration' => [
                    'total_registrations' => DB::table('inauguration_registrations')->count(),
                    'attended' => DB::table('inauguration_registrations')->where('attended', true)->count(),
                ],
                'fellowship' => [
                    'total_registrations' => DB::table('fellowship_registrations')->where('payment_status', 'completed')->count(),
                    'attended' => DB::table('fellowship_registrations')->where('attended', true)->count(),
                    'total_revenue' => DB::table('fellowship_registrations')->where('payment_status', 'completed')->sum('total_amount'),
                ],
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch global stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for a single registration by ID or membership number
     * across a specific table. Used by the QR scanner for real-time 
     * remote lookup if local cache is empty.
     */
    public function lookupRegistration(Request $request)
    {
        $id = $request->get('identifier');
        $eventType = $request->get('event_type');

        if (!$id || !$eventType) {
            return response()->json([
                'success' => false,
                'message' => 'Identifier and event_type are required'
            ], 400);
        }

        $table = '';
        switch ($eventType) {
            case 'conference': $table = 'conference_registrations'; break;
            case 'exhibition': $table = 'exhibition_registrations'; break;
            case 'agm': $table = 'agm_registrations'; break;
            case 'inauguration': $table = 'inauguration_registrations'; break;
            case 'fellowship': $table = 'fellowship_registrations'; break;
            default:
                return response()->json(['success' => false, 'message' => 'Invalid event type'], 400);
        }

        $query = DB::table($table)
            ->where('id', $id)
            ->orWhere('membership_number', $id);
            
        // Check for student_id or nic_passport if columns exist
        // Note: This is a simple generic lookup, might need adjustment per table schema if columns missing
        // For now, assuming standard fields or catching detailed logic in controllers
        
        $registration = $query->first();

        if ($registration) {
            return response()->json([
                'success' => true,
                'data' => $registration
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Registration not found'
        ], 404);
    }
}