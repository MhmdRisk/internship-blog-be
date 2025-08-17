<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\HandshakeController;
use App\Http\Controllers\OTPController;
use App\Http\Middleware\HandshakeMiddleware;
use App\Mail\BlogSubmitted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Str;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// testing API routes
Route::get('/hello',[BlogController::class,'hello']);


// AUTHENTICATION:
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('jwt')->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/user', [AuthController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);  
});


Route::get('/blog', [BlogController::class, 'readAllBlogs']);
Route::get('/blog/{id}', [BlogController::class, 'readBlog']);


Route::middleware(['jwt'])->group(function () {
    // CREATE BLOG requires author or admin role
    Route::post('/blog', [BlogController::class, 'createBlog'])->middleware('role:author,admin');
    
    // UPDATE BLOG (PATCH) requires 'edit blogs' permission
    Route::middleware(['handshake'])->group(function () {
        Route::patch('/blog/{id}', [BlogController::class, 'updateBlog'])->middleware('permission:edit blogs');
    });
    
    // DELETE BLOG requires 'delete blogs' permission (admin only)
    Route::delete('/blog/{id}', [BlogController::class, 'deleteBlog'])->middleware('permission:delete blogs');
});


// OTP routes
Route::middleware('jwt')->group(function () {
    Route::post('/generateOTP', [OTPController::class, 'generateOTP']);
    Route::post('/verifyOTP', [OTPController::class, 'verifyOTP']);
});


// TESTING
Route::post('/test-generateOTP', [OTPController::class, 'testGenerateOTP']);
Route::post('/test-verifyOTP', [OTPController::class, 'testVerifyOTP']);


// generate random nonce, store it temporarily, then return it to the client
Route::get('/handshake', function () {
    $nonce = Str::uuid()->toString(); // generate unique nonce
    Cache::put("handshake_nonce:$nonce", true, now()->addMinutes(5)); // store it for 5 mins

    return response()->json(['nonce' => $nonce]);
});


Route::post('/refresh', [AuthController::class, 'refresh']);
//Route::post('/refresh', [AuthController::class, 'refreshToken']);
Route::post('/refresh', [AuthController::class, 'refreshAccessToken']);

