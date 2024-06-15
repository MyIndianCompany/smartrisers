<?php

namespace App\Providers;

use App\Events\CommentLikeNotification;
use App\Events\CommentNotification;
use App\Events\CommentReplyNotification;
use App\Events\LikeNotification;
use App\Listeners\SendCommentLikeNotification;
use App\Listeners\SendCommentNotification;
use App\Listeners\SendCommentReplyNotification;
use App\Listeners\SendLikeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        LikeNotification::class => [
            SendLikeNotification::class,
        ],
        CommentNotification::class => [
            SendCommentNotification::class,
        ],
        CommentReplyNotification::class => [
            SendCommentReplyNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
