<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            DB::beginTransaction();
            $currentUser = auth()->user();

            if ($currentUser->id === $user->id) {
                return response()->json(['message' => 'You cannot follow yourself.'], 400);
            }

            if ($currentUser->following()->where('following_user_id', $user->id)->exists()) {
                return response()->json(['message' => 'You are already following this user.'], 400);
            }

            $currentUser->following()->attach($user->id);

            // Update following count of the current user
            $currentUser->profile->increment('following_count');

            // Update follower count of the user being followed
            $user->profile->increment('follower_count');
            DB::commit();
            return response()->json([
                'message' => 'User followed successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'message' => 'An error occurred while trying to follow the user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function following(): \Illuminate\Http\JsonResponse
    {
        $following = auth()->user()->following()->select(['users.id', 'users.name', 'users.username', 'users.profile_picture'])->get();
        return response()->json($following);
    }

    public function followers(): \Illuminate\Http\JsonResponse
    {
        $followers = auth()->user()->followers()->select(['users.id', 'users.name', 'users.username', 'users.profile_picture'])->get();
        return response()->json($followers);
    }
}
