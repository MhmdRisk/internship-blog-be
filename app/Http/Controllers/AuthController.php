<?php

namespace App\Http\Controllers;

use App\Mail\OTPMail;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\OTP;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;

class AuthController extends Controller
{
    //
    public function register(Request $request) {
        $request->validate([
            'name' => 'required|string|max:225',
            'email' => 'required|string|email|max:225|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'status'   => 'isPending',
        ]);

        // ensure user has the author role (single-role association)
        $authorRole = Role::firstOrCreate(['name' => 'author']);
        $user->role()->associate($authorRole);
        $user->save();

        // generate and send OTP email upon registration
        $otp = random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        // store OTP in database
        OTP::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);

        // send OTP via email
        try {
            Mail::to($user->email)->send(new OTPMail($otp));
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP email during registration: ' . $e->getMessage());
        }

        $token = JWTAuth::fromUser($user);

        $refreshToken = Str::random(64);
        $user->refresh_token = hash('sha256', $refreshToken);
        $user->save();

        return response()->json([
            "Status:"=>true,
            "user" => $user->load('role:id,name'),
            "access_token" => $token,
            "refresh_token" => $refreshToken
        ], 201);
    }


    public function login(Request $request) {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token'], 500);
        }

        $user = User::where('email', $request->email)->first();
        $ttl = auth('api')->factory()->getTTL() * 60; // in seconds

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // ensure user has the author role if they don't have a role
        if (!$user->role) {
            $authorRole = Role::firstOrCreate(['name' => 'author']);
            $user->role()->associate($authorRole);
            $user->save();
        }

        // generate a new refresh token and save it
        $refreshToken = Str::random(64);
        $user->refresh_token = hash('sha256', $refreshToken); // hash before saving
        $user->save();

        return response()->json([
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => $ttl,
            'user' => $user->load('role:id,name'),
        ]);
    }

    public function logout(){
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            $user = Auth::user();
            $user->refresh_token = null;
            $user->save();
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout, please try again'], 500);
        }

        return response()->json(['message' => 'Successfully logged out']);
    }


    public function getUser(){
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
            return response()->json($user);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to fetch user profile'], 500);
        }
    }

    public function updateUser(Request $request){
        try {
            $user = Auth::user();
            $user->update($request->only(['name', 'email']));
            return response()->json($user);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to update user'], 500);
        }
    }

    // access token
    public function refresh() {
        try {
            $newToken = JWTAuth::parseToken()->refresh(); // refresh the token

            return response()->json([
                'token' => $newToken,
                'expires_in' => auth('api')->factory()->getTTL() * 60, // seconds
            ]);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token refresh failed'], 401);
        }
    }

    public function refreshAccessToken(Request $request) {
        $request->validate([
        'refresh_token' => 'required|string',
    ]);

    // hash the received refresh token to compare with DB
    $hashedRefreshToken = hash('sha256', $request->refresh_token);

    // find user by refresh token
    $user = User::where('refresh_token', $hashedRefreshToken)->first();

    if (!$user) {
        return response()->json(['error' => 'Invalid refresh token'], 401);
    }

    // generate new access token
    $newAccessToken = JWTAuth::fromUser($user);

    // optionally, generate a new refresh token (rotate refresh tokens)
    $newRefreshToken = Str::random(64);
    $user->refresh_token = hash('sha256', $newRefreshToken);
    $user->save();

    return response()->json([
        'access_token' => $newAccessToken,
        'refresh_token' => $newRefreshToken,
        'expires_in' => auth('api')->factory()->getTTL() * 60,
    ]);
    }

}
