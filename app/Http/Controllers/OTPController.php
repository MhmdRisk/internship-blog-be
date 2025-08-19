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

        // invalidate any existing OTPs for this user
        OTP::where('user_id', $user->id)->delete();

        $otp = random_int(100000, 999999);
        
        // Set expiration time (10 minutes from now)
        $expiresAt = Carbon::now()->addMinutes(10);

        OTP::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);

        try {
            Mail::to($user->email)->send(new OTPMail($otp));
            
            return response()->json([
                'message' => 'OTP sent successfully to your email',
                'expires_in_minutes' => 10
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP email: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to send OTP. Please try again.'
            ], 500);
        }
    }

    
    public function verifyOTP(Request $request)
    {
        \Log::info('Starting OTP verification', ['token' => $request->bearerToken()]);
        
        try {
            $user = JWTAuth::parseToken()->authenticate();
            \Log::info('User authenticated', ['user_id' => $user->id ?? null]);
        } catch (\Exception $e) {
            \Log::error('JWT Auth Error', [
                'error' => $e->getMessage(),
                'token' => $request->bearerToken()
            ]);
            return response()->json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
        }

        if (!$user) {
            \Log::error('No user found after JWT auth');
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $userInputOTP = $request->input('otp');
        
        \Log::info('Verifying OTP', [
            'user_id' => $user->id,
            'provided_otp' => $userInputOTP
        ]);

        // find most recent valid OTP for the user
        $latestOTPRecord = OTP::where('user_id', $user->id)
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestOTPRecord) {
            \Log::error('No valid OTP found for user', ['user_id' => $user->id]);
            return response()->json([
                'error' => 'No valid OTP found. Please request a new one.',
                'isVerified' => false
            ], 400);
        }

        \Log::debug('Found OTP record', [
            'stored_otp' => $latestOTPRecord->otp,
            'expires_at' => $latestOTPRecord->expires_at
        ]);

        // verify OTP
        if ($userInputOTP === $latestOTPRecord->otp) {
            // OTP is correct, delete it to prevent reuse
            $latestOTPRecord->delete();
            
            \Log::info('OTP verified successfully', ['user_id' => $user->id]);
            
            return response()->json([
                'message' => 'OTP verified successfully',
                'isVerified' => true
            ]);
        } else {
            \Log::warning('Invalid OTP provided', [
                'user_id' => $user->id,
                'provided_otp' => $userInputOTP,
                'expected_otp' => $latestOTPRecord->otp
            ]);
            
            return response()->json([
                'error' => 'Invalid OTP',
                'isVerified' => false
            ], 400);
        }
    }

    
    // clean up expired OTPs (can be called by a scheduled job)
    public function cleanupExpiredOTPs()
    {
        $deletedCount = OTP::where('expires_at', '<', Carbon::now())->delete();
        
        return response()->json([
            'message' => "Cleaned up {$deletedCount} expired OTPs"
        ]);
    }

}
