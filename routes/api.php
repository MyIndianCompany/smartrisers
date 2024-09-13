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
        Route::post('verify/email', 'verifyEmail');
        Route::post('verify/email/resend/otp', 'verifyEmailResendOtp');
        Route::middleware('auth:api')->group(function () {
            Route::post('logout', 'logout');
        });
    });
    Route::prefix('password')->group(function () {
        Route::post('forgot', 'forgotPassword');
        Route::post('forgot/resend/otp', 'forgotPasswordResendOtp');
        Route::post('reset', 'resetPassword');
    });
});

Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('all', 'getAllUserProfile');
        Route::get('count', 'getUserCounts');
        Route::get('new', 'getNewUsers');
        // Route::get('{username}', 'userProfile');
        Route::get('{username}/followers', 'getFollowersByUsername');
        Route::get('{username}/followings', 'getFollowingsByUsername');
        Route::middleware('auth:api')->group(function () {
            Route::get('{username}', 'userProfile');
            Route::get('post/all', 'getAllUserHasPosts');
            Route::post('profile', 'updateProfile');
            Route::patch('{user}/status', 'updateStatus');
            Route::patch('private', 'updateUserIsPrivate');
            Route::delete('account', 'deleteAccount');
        });
    });
});

/* Posts */
Route::controller(\App\Http\Controllers\Post\PostController::class)->group(function () {
    Route::prefix('post')->group(function () {
        Route::get('all', 'index');
        Route::middleware(['auth:api', 'check.blocked'])->group(function () {
            Route::get('user', 'getPosts');
            Route::get('auth/user', 'getPostsByAuthUsers');
            Route::get('{id}', 'show');
            Route::post('upload', 'store');
            Route::post('{post}/like', 'like');
            Route::post('{post}/comment', 'comment');
            Route::post('comment/{postComment}/like', 'commentLike');
            Route::post('{post}/comment/{comment}/reply', 'reply');
            Route::delete('comment/{comment}', 'deleteComment');
            Route::delete('{post}', 'destroy');
        });
        Route::get('{username}/user', 'getPostsByUsername');
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

/* User Report */
Route::controller(\App\Http\Controllers\Report\UserReportController::class)->group(function () {
    Route::prefix('report')->group(function () {
        Route::middleware('auth:api')->group(function () {
            Route::get('all', 'index');
            Route::post('user', 'store');
            Route::put('{userReport}/status', 'updateReportStatus');
        });
    });
});


/* User Blocked */
Route::controller(\App\Http\Controllers\BlockController::class)->group(function () {
    Route::middleware('auth:api')->group(function () {
        Route::get('block/list', 'getBlockedList');
        Route::post('block/{id}', 'blockUser');
        Route::post('unblock/{id}', 'unblockUser');
    });
});

/* Notification */
Route::controller(\App\Http\Controllers\NotificationController::class)->group(function () {
    Route::middleware('auth:api')->group(function () {
        Route::get('notification', 'index');
        Route::post('/notifications/{id}/read', 'markAsRead');
    });
});


/* Web Help Enquiry */

Route::controller(\App\Http\Controllers\WebHelpEnquiryController::class)->group(function () {
    Route::get('enquiries', 'index');
    Route::post('enquiry', 'store');
});
