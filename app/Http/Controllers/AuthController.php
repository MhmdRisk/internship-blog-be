<?php

namespace App\Http\Controllers;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    //
    public function register(Request $request) {
        //dd('hello');
        $request->validate([
            'name' => 'required|string|max:225',
            'email' => 'required|string|email|max:225',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = JWTAuth::fromUser($user);
        /*
        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token'], 500);
        }
        */

        $refreshToken = Str::random(64);
        $user->refresh_token = hash('sha256', $refreshToken);
        $user->save();

        // return response()->json(compact('user', 'token'), 201);
        return response()->json([
            "Status:"=>true,
            "user" => $user,
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

        //$user = RefreshToken::where('email', $request->email)->first();
        $user = User::where('email', $request->email)->first();

        $ttl = auth('api')->factory()->getTTL() * 60; // in seconds
        //$issuedAt = now()->timestamp; // current time in UNIX timestamp
        //$expiresAt = $issuedAt + $ttl;

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // generate a new refresh token and save it
        $refreshToken = Str::random(64);
        $user->refresh_token = hash('sha256', $refreshToken); // hash before saving
        $user->save();

        // return response()->json(compact('token'));
        return response()->json([
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            //'expires_in' => auth('api')->factory()->getTTL() * 60,
            'expires_in' => $ttl,
            //'time_to_live' => $expiresAt - time(),
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

    // Hash the received refresh token to compare with DB
    $hashedRefreshToken = hash('sha256', $request->refresh_token);

    // Find user by refresh token
    $user = User::where('refresh_token', $hashedRefreshToken)->first();

    if (!$user) {
        return response()->json(['error' => 'Invalid refresh token'], 401);
    }

    // Generate new access token
    $newAccessToken = JWTAuth::fromUser($user);

    // Optionally, generate a new refresh token (rotate refresh tokens)
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
