<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Fetch notifications for the authenticated user
        $notifications = Notification::where('user_id', $request->user()->id)->get();

        $notificationsData = $notifications->map(function($notification) {
            // Decode JSON data only if it's a string
            $data = is_string($notification->data) ? json_decode($notification->data, true) : $notification->data;

            // Initialize additional fields to null
            $postVideoUrl = null;
            $likedByProfilePicture = null;
            $likedByUsername = null;

            if (isset($data['post_id'])) {
                // Fetch post details
                $post = Post::find($data['post_id']);
                $postVideoUrl = $post ? $post->file_url : null;
            }

            if (isset($data['liked_by'])) {
                // Fetch user profile picture
                $user = User::with('profile')->find($data['liked_by']);
                $likedByProfilePicture = $user && $user->profile ? $user->profile->profile_picture : null;
                $likedByUsername = $user && $user->profile ? $user->profile->username : null;
            }

            Log::info('Notification Data:', $data);

            return [
                'liked_by' => $data['liked_by'] ?? null,
                'post_id' => $data['post_id'] ?? null,
                'liked_by_profile_picture' => $likedByProfilePicture,
                'username' => $likedByUsername,
                'post_video_url' => $postVideoUrl,
                'type' => $notification->type,
                'created_at' => $notification->created_at,
            ];
        });

        return response()->json($notificationsData);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->read = true;
        $notification->save();

        return response()->json(['message' => 'Notification marked as read']);
    }
}
