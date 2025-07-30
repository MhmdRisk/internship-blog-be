<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\HandshakeController;
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

/*
user needs to be able to:
read (get), create (post), update (patch), delete (delete)
*/

// READ 
//Route::get('/blog',[BlogController::class,'readAllBlogs']);
//Route::get('/blog/{id}',[BlogController::class,'readBlog']);

// AUTHENTICATION:
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/user', [AuthController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // CREATE
    //Route::post('/blog',[BlogController::class,'createBlog']);
    // UPDATE
    //Route::patch('/blog/{id}',[BlogController::class,'updateBlog']);
    //Route::put('/blog/{id}',[BlogController::class,'updateBlog']);
    // DELETE
    //Route::delete('/blog/{id}',[BlogController::class,'deleteBlog']);    
});

Route::middleware(['handshake'])->group(function () {
    Route::get('/blog', [BlogController::class, 'readAllBlogs']);
    Route::post('/blog',[BlogController::class,'createBlog']);
    Route::patch('/blog/{id}',[BlogController::class,'updateBlog']);
    Route::delete('/blog/{id}',[BlogController::class,'deleteBlog']);
});


// generate random nonce, store it temporarily, then return it to the client
Route::get('/handshake', function () {
    $nonce = Str::uuid()->toString(); // generate unique nonce
    Cache::put("handshake_nonce:$nonce", true, now()->addMinutes(5)); // store it for 5 mins

    return response()->json(['nonce' => $nonce]);
});

Route::post('/refresh', [AuthController::class, 'refresh']);
//Route::post('/refresh', [AuthController::class, 'refreshToken']);
Route::post('/refresh', [AuthController::class, 'refreshAccessToken']);

