<?php

namespace App\Listeners;

use App\Events\LikeNotification;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
     */
    public function handle(LikeNotification $event)
    {
        Notification::create([
            'user_id' => $event->post->user_id,
            'type' => 'like',
            'data' => [
                'liked_by' => $event->user->id,
                'post_id' => $event->post->id,
            ],
        ]);
    }
}
