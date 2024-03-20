<?php

namespace App\Services\Posts;

use App\Models\Post;

class PostServices
{
    public function getPostsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Post::with([
            'user' => function ($query) {
                $query->select('id', 'name', 'username', 'profile_picture');
            },
            'comments' => function ($query) {
                $query->whereNull('super_comment_id');
            },
            'comments.user' => function ($query) {
                $query->select('id', 'name', 'username', 'profile_picture');
            },
            'comments.replies.user' => function ($query) {
                $query->select('id', 'name', 'username', 'profile_picture');
            },
            'comments.replies.replies.user' => function ($query) {
                $query->select('id', 'name', 'username', 'profile_picture');
            },
            'comments.replies.replies.replies.user' => function ($query) {
                $query->select('id', 'name', 'username', 'profile_picture');
            },
        ])->inRandomOrder();
    }
}
