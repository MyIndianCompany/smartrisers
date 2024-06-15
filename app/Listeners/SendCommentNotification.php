<?php

namespace App\Listeners;

use App\Events\CommentNotification;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendCommentNotification
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
    public function handle(CommentNotification $event): void
    {
        Notification::create([
            'user_id' => $event->post->user_id,
            'type' => 'comment',
            'data' => [
                'commented_by' => $event->user->id,
                'post_id' => $event->post->id,
            ],
        ]);
    }
}
