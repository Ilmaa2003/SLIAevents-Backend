<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminSession
{
    public function handle(Request $request, Closure $next)
    {
        // Simple session check
        if (!session('admin_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login.'
            ], 401);
        }
        
        return $next($request);
    }
}