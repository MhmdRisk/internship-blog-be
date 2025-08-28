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


// Temporary route to check Cloudinary config
Route::get('/check-cloudinary', function() {
    return response()->json([
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
        'api_key' => getenv('CLOUDINARY_API_KEY') ? 'set' : 'not set',
        'api_secret' => getenv('CLOUDINARY_API_SECRET') ? 'set' : 'not set'
    ]);
});
    
    
Route::middleware(['handshake'])->group(function () {
    // AUTHENTICATION:
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // CHECK OUT BLOGS (regardless of whether ure registered or not)
    Route::get('/blog', [BlogController::class, 'readAllBlogs']);
    Route::get('/blog/{id}', [BlogController::class, 'readBlog']);

    Route::middleware(['jwt'])->group(function () {
        // Routes accessible to all authenticated users (including pending verification)
        Route::get('/user', [AuthController::class, 'getUser']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // OTP
        Route::post('/generateOTP', [OTPController::class, 'generateOTP']);
        Route::post('/verifyOTP', [OTPController::class, 'verifyOTP']); 
    
        // route requiring verified user status
        Route::post('/user', [AuthController::class, 'updateUser']);

        // UPDATE BLOG requires author or admin role AND verified user status
        Route::patch('/blog/{id}', [BlogController::class, 'updateBlog']);

        // CREATE BLOG requires author/admin role, verified status, and valid hashed key
        Route::post('/blog', [BlogController::class, 'createBlog']);
                
        // DELETE BLOG requires admin role AND verified user status
        Route::delete('/blog/{id}', [BlogController::class, 'deleteBlog']);
    });
});


// generate random nonce, store it temporarily, then return it to the client
Route::get('/handshake', function () {
    $nonce = Str::uuid()->toString(); // generate unique nonce
    Cache::put("handshake_nonce:$nonce", true, now()->addMinutes(5)); // store it for 5 mins

    return response()->json(['nonce' => $nonce]);
});


Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/refresh', [AuthController::class, 'refreshAccessToken']);

