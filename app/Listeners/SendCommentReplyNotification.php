<?php

namespace App\Listeners;

use App\Events\CommentReplyNotification;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendCommentReplyNotification
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
    public function handle(CommentReplyNotification $event): void
    {
        $data = [
            'comment_reply_by' => $event->user->id,
            'post_id' => $event->post->id,
            'comment_reply_by_profile_picture' => $event->user->profile->profile_picture,
            'username' => $event->user->username,
            'post_video_url' => $event->post->file_url,
        ];

        Notification::create([
            'user_id' => $event->post->user_id,
            'type' => 'comment_reply',
            'data' => $data,
        ]);
    }
}
