<?php

namespace App\Services\Posts;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\UserProfile;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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
        ]);
    }

//    public function uploadPost(Request $request, int $user_id)
//    {
//        $uploadedFile = $request->file('file');
//        $originalFileName = $uploadedFile->getClientOriginalName();
//        $uploadedVideo = Cloudinary::uploadVideo($uploadedFile->getRealPath());
//        $videoUrl = $uploadedVideo->getSecurePath();
//        $publicId = $uploadedVideo->getPublicId();
//        $fileSize = $uploadedVideo->getSize();
//        $fileType = $uploadedVideo->getFileType();
//        $width = $uploadedVideo->getWidth();
//        $height = $uploadedVideo->getHeight();
//        $user = Auth::user();
//        return Post::create([
//            'user_id'            => $user_id,
//            'caption'            => $request->input('caption'),
//            'original_file_name' => $originalFileName,
//            'file_url'           => $videoUrl,
//            'public_id'          => $publicId,
//            'file_size'          => $fileSize,
//            'file_type'          => $fileType,
//            'mime_type'          => $uploadedFile->getMimeType(),
//            'width'              => $width,
//            'height'             => $height,
//        ]);
//    }

    public function uploadPost(Request $request, int $user_id)
    {
        // Validate the request
        $request->validate([
            'file' => 'required|file|mimes:mp4,mov,ogg,qt', // Adjust file types and size as needed
            'caption' => 'required|string|max:255',
        ]);

        // Handle the uploaded file
        $uploadedFile = $request->file('file');
        $originalFileName = $uploadedFile->getClientOriginalName();
        $uploadedVideo = Cloudinary::uploadVideo($uploadedFile->getRealPath());
        $videoUrl = $uploadedVideo->getSecurePath();
        $publicId = $uploadedVideo->getPublicId();
        $fileSize = $uploadedVideo->getSize();
        $fileType = $uploadedVideo->getFileType();
        $width = $uploadedVideo->getWidth();
        $height = $uploadedVideo->getHeight();

        // Create the post
        $post = Post::create([
            'user_id'            => $user_id,
            'caption'            => $request->input('caption'),
            'original_file_name' => $originalFileName,
            'file_url'           => $videoUrl,
            'public_id'          => $publicId,
            'file_size'          => $fileSize,
            'file_type'          => $fileType,
            'mime_type'          => $uploadedFile->getMimeType(),
            'width'              => $width,
            'height'             => $height,
        ]);
        // Update the post count for the user
        $user = UserProfile::find($user_id);
        if ($user) {
            $user->increment('post_count');
            // Update the post count for the user's profile
            $userProfile = $user->profile; // Assuming the relationship is defined as 'profile'
            if ($userProfile) {
                $userProfile->increment('post_count');
            }
        }
    }


    public function addComment(Post $post, $comment)
    {
        $user = Auth::user();
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
        $user = Auth::user();
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

    public function likePost(Post $post)
    {
        $user = Auth::user();
        DB::beginTransaction();
        $like = $user->likes()->where('post_id', $post->id)->first();
        $like ? $like->delete() : $user->likes()->create(['post_id' => $post->id]);
        $post->update(['like_count' => $post->likes()->count()]);
        DB::commit();
        return $like ? 'Post unliked successfully.' : 'Post liked successfully.';
    }

    public function likePostComment(PostComment $postComment)
    {
        $user = Auth::user();
        DB::beginTransaction();
        $like = $user->commentLikes()->where('comment_id', $postComment->id)->first();
        $like ? $like->delete() : $user->commentLikes()->create(['comment_id' => $postComment->id]);
        $postComment->update(['comment_like_count' => $postComment->likes()->count()]);
        DB::commit();
        return $like ? 'Post Comment unliked successfully.' : 'Post Comment liked successfully.';
    }
}
