<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\Posts\PostServices;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)->get();

        $notificationsData = $notifications->map(function($notification) use ($request) {
            $data = is_string($notification->data) ? json_decode($notification->data, true) : $notification->data;

            // $postVideoUrl = null;
            $postData = null;
            // $postThumbnailUrl = null;
            $likedByProfilePicture = null;
            $likedByUsername = null;
            $likedByUserName = null;

            // if (isset($data['post_id'])) {
            //     $post = Post::find($data['post_id']);

            //     if ($post) {
            //         $postVideoUrl = $post->file_url;
            //         $postThumbnailUrl = $post->thumbnail_url;
            //     }
            // }
            if (isset($data['post_id'])) {
                $post = new PostServices();
                $postQuery = $post->getPostsQuery($request->user()->id);
                $postData = $postQuery->where('id', $data['post_id'])->first();
            }

            if (isset($data['liked_by'])) {
                $user = User::with('profile')->find($data['liked_by']);
                $likedByProfilePicture = $user && $user->profile ? $user->profile->profile_picture : null;
                $likedByUsername = $user && $user->profile ? $user->profile->username : null;
                $likedByUserName = $user && $user->profile ? $user->profile->name : null;
            }

            return [
                'liked_by' => $data['liked_by'] ?? null,
                'post_id' => $data['post_id'] ?? null,
                'name' => $likedByUserName,
                'username' => $likedByUsername,
                'liked_by_profile_picture' => $likedByProfilePicture,
                // 'post_video_url' => $postVideoUrl,
                // 'post_thumbnail_url' => $postThumbnailUrl,
                'post_data' => $postData,
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
