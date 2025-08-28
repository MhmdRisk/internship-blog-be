<?php

namespace App\Http\Controllers;

use App\Mail\OTPMail;
use App\Models\OTP;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
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
        
        // set expiration time (10 minutes from now)
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
            return response()->json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
        }

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $userInputOTP = $request->input('otp');

        $latestOTPRecord = OTP::where('user_id', $user->id)
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestOTPRecord) {
            return response()->json([
                'error' => 'No valid OTP found. Please request a new one.',
                'status' => 'isPending'
            ], 400);
        }

        // verify OTP
        if ((string) $userInputOTP === (string) $latestOTPRecord->otp) {
            // OTP is correct, delete it to prevent reuse
            $latestOTPRecord->delete();
            
            // update "status" in DB
            $user->update([
                'status' => 'isActive'
            ]);
            
            return response()->json([
                'message' => 'OTP verified successfully',
                'status' => 'isActive'
            ]);
        } else {
            return response()->json([
                'error' => 'Invalid OTP',
                'status' => 'isPending'
            ], 400);
        }
    }

}
