<?php

namespace App\Services\Posts;

use App\Models\Post;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

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
}
