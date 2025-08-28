<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        
        // Check if user status is 'isActive'
        if ($user->status !== 'isActive') {
            return response()->json([
                'error' => 'Account not verified.',
                'status' => $user->status,
                'message' => 'Your account is pending verification. Please verify your email with the OTP sent to your email address.'
            ], 403);
        }

        return $next($request);
    }
}
