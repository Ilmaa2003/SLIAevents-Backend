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
}