<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FollowerController extends Controller
{
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $request->input('query');

        $users = User::where('name', 'like', "%$query%")
            ->orWhere('username', 'like', "%$query%")
            ->get(['id', 'name', 'username', 'profile_picture']);

        return response()->json($users);
    }

    public function follow(User $user): \Illuminate\Http\JsonResponse
    {
        try {
            $currentUser = auth()->user();

            if ($currentUser->id === $user->id) {
                return response()->json(['message' => 'You cannot follow yourself.'], 400);
            }

            if ($currentUser->following()->where('following_user_id', $user->id)->exists()) {
                return response()->json([
                    'message' => 'You are already following this user.',
                    'isFollowed' => true,
                    'action' => 'unfollow'
                ], 400);
            }

            $currentUser->load('profile');
            $user->load('profile');

            DB::beginTransaction();

            $currentUser->following()->attach($user->id);

            if ($currentUser->profile) {
                $currentUser->profile->increment('following_count');
            } else {
                throw new \Exception('Profile not loaded for current user.');
            }

            if ($user->profile) {
                $user->profile->increment('follower_count');
            } else {
                throw new \Exception('Profile not loaded for the user being followed.');
            }

            DB::commit();

            return response()->json([
                'message' => 'User followed successfully',
                'isFollowed' => true,
                'action' => 'unfollow'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            Log::error('Follow error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'An error occurred while trying to follow the user.',
                'error' => $e->getMessage(),
                'isFollowed' => false,
                'action' => 'follow'
            ], 500);
        }
    }

    public function unfollow(User $user): \Illuminate\Http\JsonResponse
    {
        try {
            $currentUser = auth()->user();

            if ($currentUser->id === $user->id) {
                return response()->json(['message' => 'You cannot unfollow yourself.'], 400);
            }

            if (!$currentUser->following()->where('following_user_id', $user->id)->exists()) {
                return response()->json([
                    'message' => 'You are not following this user.',
                    'isFollowed' => false,
                    'action' => 'follow'
                ], 400);
            }

            $currentUser->load('profile');
            $user->load('profile');

            DB::beginTransaction();

            $currentUser->following()->detach($user->id);

            if ($currentUser->profile) {
                $currentUser->profile->decrement('following_count');
            } else {
                throw new \Exception('Profile not loaded for current user.');
            }

            if ($user->profile) {
                $user->profile->decrement('follower_count');
            } else {
                throw new \Exception('Profile not loaded for the user being unfollowed.');
            }

            DB::commit();

            return response()->json([
                'message' => 'User unfollowed successfully',
                'isFollowed' => false,
                'action' => 'follow'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            Log::error('Unfollow error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'An error occurred while trying to unfollow the user.',
                'error' => $e->getMessage(),
                'isFollowed' => true,
                'action' => 'unfollow'
            ], 500);
        }
    }

    public function following(string $username): \Illuminate\Http\JsonResponse
    {
        $user = User::where('username', $username)->firstOrFail();
        $authUser = Auth::user();
        $following = $user->following()->select(['users.id', 'users.name', 'users.username', 'users.profile_picture'])->get();

        // Add the following status for each followed user
        $following = $following->map(function ($followedUser) use ($authUser) {
            $followedUser->isFollowing = $authUser->following()->where('users.id', $followedUser->id)->exists();
            return $followedUser;
        });

        return response()->json($following);
    }

    public function followers(string $username): \Illuminate\Http\JsonResponse
    {
        $user = User::where('username', $username)->firstOrFail();
        $authUser = Auth::user();
        $followers = $user->followers()->select(['users.id', 'users.name', 'users.username', 'users.profile_picture'])->get();
        
        $followers = $followers->map(function ($follower) use ($authUser) {
            $follower->isFollowing = $authUser->following()->where('users.id', $follower->id)->exists();
            return $follower;
        });
        
        return response()->json($followers);
    }
}
