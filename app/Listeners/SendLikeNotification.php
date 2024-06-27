<?php

namespace App\Listeners;

use App\Events\LikeNotification;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLikeNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  LikeNotification  $event
     * @return void
     */
        public function handle(LikeNotification $event)
    {
        $data = [
            'liked_by' => $event->user->id,
            'post_id' => $event->post->id,
            'liked_by_profile_picture' => $event->user->profile_picture,
            'username' => $event->user->username,
            'post_video_url' => $event->post->video_url,
        ];

        Log::info('Notification Data:', $data); // Log the data for debugging

        Notification::create([
            'user_id' => $event->post->user_id,
            'type' => 'like',
            'data' => $data,
        ]);
    }
}
