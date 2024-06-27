<?php

namespace App\Events;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentReplyNotification
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $username;
    public $post;
    public $comment;
    public $reply;
    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Post $post, PostComment $comment, PostComment $reply)
    {
        $this->user = $user;
        $this->username = $user->profile->username;
        $this->post = $post;
        $this->comment = $comment;
        $this->reply = $reply;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
