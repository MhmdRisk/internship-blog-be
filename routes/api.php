<?php

use App\Http\Controllers\BlogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// testing API routes
Route::get('/hello',[BlogController::class,'hello']);

/*
user needs to be able to:
read (get), create (post), update (patch), delete (delete)
*/




// AUTHENTICATION:
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('jwt')->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/user', [AuthController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // CREATE
    Route::post('/blog',[BlogController::class,'createBlog']);
    // READ 
    Route::get('/blog',[BlogController::class,'readAllBlogs']);
    Route::get('/blog/{id}',[BlogController::class,'readBlog']);
    // UPDATE
    Route::patch('/blog/{id}',[BlogController::class,'updateBlog']);
    //Route::put('/blog/{id}',[BlogController::class,'updateBlog']);
    // DELETE
    Route::delete('/blog/{id}',[BlogController::class,'deleteBlog']);
});
