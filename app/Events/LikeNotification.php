<?php

namespace App\Events;

use App\Models\Post;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LikeNotification
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $post;
    public $userProfilePicture;
    public $username;
    public $postVideoUrl;
    /**
     * Create a new event instance.
     */
    public function __construct(User $user,  Post $post)
    {
        $this->user = $user;
        $this->post = $post;
        $this->userProfilePicture = $user->profile->profile_picture;
        $this->username = $user->username;
        $this->postVideoUrl = $post->file_url;
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
