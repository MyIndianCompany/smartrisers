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

Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('{username}', 'userProfile');
        Route::middleware('auth:api')->group(function () {
            Route::patch('profile', 'updateProfile');
        });
    });
});

/* Posts */
Route::controller(\App\Http\Controllers\Post\PostController::class)->group(function () {
    Route::prefix('post')->group(function () {
        Route::get('all', 'index');
        Route::middleware('auth:api')->group(function () {
            Route::get('user', 'getPosts');
            Route::get('auth/user', 'getPostsByAuthUsers');
            Route::get('{userId}/user', 'getPostsByUserId');
            Route::post('upload','store');
            Route::post('{post}/like', 'like');
            Route::post('{post}/comment', 'comment');
            Route::post('comment/{postComment}/like', 'commentLike');
            Route::post('{post}/comment/{comment}/reply', 'reply');
            Route::delete('comment/{comment}', 'deleteComment');
            Route::delete('{post}', 'destroy');
        });
    });
});

/* Post Comment */
Route::controller(\App\Http\Controllers\Post\PostCommentController::class)->group(function () {
    Route::prefix('post')->group(function () {
        Route::middleware('auth:api')->group(function () {
            Route::post('{post}/comment', 'comment');
            Route::post('comment/{postComment}/like', 'commentLike');
            Route::post('{post}/comment/{comment}/reply', 'reply');
            Route::delete('comment/{comment}', 'deleteComment');
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


