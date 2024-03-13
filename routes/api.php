<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/* User Authentication */
Route::controller(\App\Http\Controllers\Auth\AuthController::class)->group(function () {
   Route::prefix('auth')->group(function () {
      Route::post('register', 'register');
      Route::post('login', 'login');
       Route::middleware('auth:api')->group(function () {
           Route::post('logout','logout');
       });
   });
});

/* Posts */
Route::controller(\App\Http\Controllers\PostController::class)->group(function () {
    Route::prefix('post')->group(function () {
        Route::get('all', 'index');
        Route::get('user', 'index');
        Route::middleware('auth:api')->group(function () {
            Route::post('upload','store');
            Route::delete('{post}', 'destroy');
        });
    });
});

/* Followers */
Route::controller(\App\Http\Controllers\FollowerController::class)->group(function () {
    Route::get('search', 'search');
    Route::middleware('auth:api')->group(function () {
        Route::get('/following', 'following');
        Route::get('/followers', 'followers');
        Route::post('/follow/{user}', 'follow');
    });
});

