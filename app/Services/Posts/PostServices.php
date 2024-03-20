<?php

namespace App\Services\Posts;

use App\Models\Post;
use App\Models\PostComment;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\DB;

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

    public function uploadPost($request)
    {
        $uploadedFile = $request->file('file');
        $originalFileName = $uploadedFile->getClientOriginalName();
        $uploadedVideo = Cloudinary::uploadVideo($uploadedFile->getRealPath());
        $videoUrl = $uploadedVideo->getSecurePath();
        $publicId = $uploadedVideo->getPublicId();
        $fileSize = $uploadedVideo->getSize();
        $fileType = $uploadedVideo->getFileType();
        $width = $uploadedVideo->getWidth();
        $height = $uploadedVideo->getHeight();

        $user = auth()->user()->id;
        $post = Post::create([
            'user_id' => $user,
            'caption' => $request->input('caption'),
            'original_file_name' => $originalFileName,
            'file_url' => $videoUrl,
            'public_id' => $publicId,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'mime_type' => $uploadedFile->getMimeType(),
            'width' => $width,
            'height' => $height,
        ]);

        return $post;
    }

    public function addComment(Post $post, $comment)
    {
        $user = auth()->user();

        DB::beginTransaction();

        $comment = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'comment' => $comment
        ]);

        $post->update(['comment_count' => $post->comments()->count()]);

        DB::commit();

        return $comment;
    }

    public function addReply(Post $post, PostComment $comment, $reply)
    {
        $user = auth()->user();

        DB::beginTransaction();

        $reply = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'super_comment_id' => $comment->id,
            'comment' => $reply
        ]);

        $comment->update(['comment_reply_count' => $comment->replies()->count()]);

        DB::commit();

        return $reply;
    }
}
