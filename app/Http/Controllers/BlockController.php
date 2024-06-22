<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockController extends Controller
{
    public function blockUser($blocked_id)
    {
        $blocker_id = Auth::id();

        if ($blocker_id == $blocked_id) {
            return response()->json(['message' => 'You cannot block yourself'], 400);
        }

        $blocker = User::find($blocker_id);
        $blocked = User::find($blocked_id);

        if (!$blocked) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $blocker->blockedUsers()->attach($blocked_id);

        return response()->json(['message' => 'User blocked successfully'], 200);
    }

    public function unblockUser($blocked_id)
    {
        $blocker_id = Auth::id();

        $blocker = User::find($blocker_id);
        $blocked = User::find($blocked_id);

        if (!$blocked) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $blocker->blockedUsers()->detach($blocked_id);

        return response()->json(['message' => 'User unblocked successfully'], 200);
    }

    public function getBlockedList()
    {
        $user_id = Auth::id();
        $user = User::find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $blockedUsers = $user->blockedUsers()->get();

        return response()->json(['blocked_users' => $blockedUsers], 200);
    }

}
