<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckBlockedUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $currentUserId = Auth::id();
        $otherUserId = $request->route('id'); // Assuming the user ID is passed in the route

        $currentUser = Auth::user();
        $otherUser = User::find($otherUserId);

        if ($currentUser->blockedUsers()->where('blocked_id', $otherUserId)->exists() ||
            $currentUser->blockedByUsers()->where('blocker_id', $otherUserId)->exists()) {
            return response()->json(['message' => 'Action not allowed'], 403);
        }

        return $next($request);
    }
}
