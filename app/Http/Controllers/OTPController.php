<?php

namespace App\Http\Controllers;

use App\Mail\OTPMail;
use App\Models\OTP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class OTPController extends Controller
{

    public function generateOTP(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $otp = random_int(100000, 999999);
        
        // set expiration time (10 minutes from now)
        $expiresAt = Carbon::now()->addMinutes(10);

        // store OTP in DB
        OTP::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);

        // Send OTP via email
        try {
            Mail::to($user->email)->send(new OTPMail($otp));
            
            return response()->json([
                'message' => 'OTP sent successfully to your email',
                'expires_in_minutes' => 10
            ]);
        } catch (\Exception $e) {
            // Log the error and return generic message
            \Log::error('Failed to send OTP email: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to send OTP. Please try again.'
            ], 500);
        }
    }

    
    public function verifyOTP(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $userInputOTP = $request->input('otp');


        // find most recent valid OTP for the user
        $latestOTPRecord = OTP::where('user_id', $user->id)
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->first();

        // verify OTP
        if ($userInputOTP === $latestOTPRecord->otp) {            
            return response()->json([
                'message' => 'OTP verified successfully',
                'isVerified' => true
            ]);
        } else {  
            return response()->json([
                'error' => 'Invalid OTP',
                'isVerified' => false
            ], 400);
        }
    }

    
    // Clean up expired OTPs (can be called by a scheduled job)
    public function cleanupExpiredOTPs()
    {
        $deletedCount = OTP::where('expires_at', '<', Carbon::now())->delete();
        
        return response()->json([
            'message' => "Cleaned up {$deletedCount} expired OTPs"
        ]);
    }

    /**
     * TEMPORARY: Test OTP generation without authentication (REMOVE IN PRODUCTION)
     */
    public function testGenerateOTP(Request $request)
    {
        // Use a hardcoded user for testing (user ID 2)
        $user = \App\Models\User::find(2);
        
        if (!$user) {
            return response()->json(['error' => 'Test user not found'], 404);
        }

        // Rate limiting: max 3 OTP requests per 5 minutes per user
        $key = 'otp-generation:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'error' => 'Too many OTP requests. Please try again in ' . ceil($seconds / 60) . ' minutes.'
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        // Invalidate any existing OTPs for this user
        OTP::where('user_id', $user->id)->delete();

        // Generate secure 6-digit OTP
        $otp = random_int(100000, 999999);
        
        // Set expiration time (10 minutes from now)
        $expiresAt = Carbon::now()->addMinutes(10);

        // Store OTP in database
        OTP::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);

        // Send OTP via email
        try {
            Mail::to($user->email)->send(new OTPMail($otp));
            
            return response()->json([
                'message' => 'OTP sent successfully to your email',
                'expires_in_minutes' => 10,
                'test_user_email' => $user->email,
                'test_note' => 'This is a test endpoint - check your email for the OTP'
            ]);
        } catch (\Exception $e) {
            // Log the error and return generic message
            \Log::error('Failed to send OTP email: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to send OTP. Please try again.',
                'debug_error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * TEMPORARY: Test OTP verification without authentication (REMOVE IN PRODUCTION)
     */
    public function testVerifyOTP(Request $request)
    {
        // Use a hardcoded user for testing (user ID 2)
        $user = \App\Models\User::find(2);
        
        if (!$user) {
            return response()->json(['error' => 'Test user not found'], 404);
        }

        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $userInputOTP = $request->input('otp');

        // Rate limiting: max 5 verification attempts per 5 minutes per user
        $key = 'otp-verification:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'error' => 'Too many verification attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'
            ], 429);
        }

        // Find the most recent valid OTP for the user
        $latestOTPRecord = OTP::where('user_id', $user->id)
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestOTPRecord) {
            RateLimiter::hit($key, 300);
            return response()->json([
                'error' => 'No valid OTP found. Please request a new one.',
                'isVerified' => false
            ], 404);
        }

        // Verify OTP
        if ($userInputOTP === $latestOTPRecord->otp) {
            // OTP is correct - delete it to prevent reuse
            $latestOTPRecord->delete();
            
            // Clear rate limiting on successful verification
            RateLimiter::clear($key);
            
            return response()->json([
                'message' => 'OTP verified successfully',
                'isVerified' => true,
                'test_note' => 'This is a test endpoint - OTP verification successful!'
            ]);
        } else {
            // Incorrect OTP - increment rate limiting
            RateLimiter::hit($key, 300);
            
            return response()->json([
                'error' => 'Invalid OTP',
                'isVerified' => false,
                'debug_info' => [
                    'provided_otp' => $userInputOTP,
                    'expected_length' => 6,
                    'otp_expires_at' => $latestOTPRecord->expires_at
                ]
            ], 400);
        }
    }
}
