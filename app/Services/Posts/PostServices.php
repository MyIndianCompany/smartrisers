<?php

namespace App\Services\Posts;

use App\Events\CommentLikeNotification;
use App\Events\CommentNotification;
use App\Events\CommentReplyNotification;
use App\Events\LikeNotification;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\UserProfile;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PostServices
{
    public function getPostsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $authUserId = auth()->id();

        return Post::whereHas('user', function ($query) {
            $query->where('status', 'active')
                ->whereHas('profile', function ($query) {
                    $query->where('is_private', false);
                });
        })
            // ->orWhere(function ($query) use ($authUserId) {
            //     $query->whereHas('user.followers', function ($query) use ($authUserId) {
            //         $query->where('follower_user_id', $authUserId);
            //     });
            // })
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'name', 'username', 'profile_picture');
                },
                'comments' => function ($query) {
                    $query->whereNull('super_comment_id')
                        ->with([
                            'user' => function ($query) {
                                $query->select('id', 'name', 'username', 'profile_picture');
                            },
                            'replies' => function ($query) {
                                $query->with([
                                    'user' => function ($query) {
                                        $query->select('id', 'name', 'username', 'profile_picture');
                                    },
                                    'replies' => function ($query) {
                                        $query->with([
                                            'user' => function ($query) {
                                                $query->select('id', 'name', 'username', 'profile_picture');
                                            }
                                        ])->limit(5); // Limit number of nested replies fetched
                                    }
                                ])->limit(5); // Limit number of nested replies fetched
                            }
                        ])->limit(10); // Limit number of comments fetched
                }
            ])->select(
                'id',
                'user_id',
                'caption',
                'original_file_name',
                'file_url',
                'thumbnail_url',
                'public_id',
                'like_count',
                'comment_count',
                'created_at'
            );
    }

    public function uploadPost(Request $request, int $user_id)
    {
        return "Hello";
        // $request->validate([
        //     'file' => 'required|file|mimes:mp4,mov,ogg,qt|max:51200',
        //     'caption' => 'required|string|max:255',
        // ]);

        // $uploadedFile = $request->file('file');
        // if (!$uploadedFile) {
        //     return response()->json(['error' => 'File upload failed.'], 400);
        // }
        // $originalFileName = $uploadedFile->getClientOriginalName();
        // $uploadedVideo = Cloudinary::uploadVideo($uploadedFile->getRealPath(), [
        //     'resource_type' => 'video',
        //     'chunk_size'    => 6000000,
        // ]);
        // $videoUrl = $uploadedVideo->getSecurePath();
        // $publicId = $uploadedVideo->getPublicId();
        // $fileSize = $uploadedVideo->getSize();
        // $fileType = $uploadedVideo->getFileType();
        // $width = $uploadedVideo->getWidth();
        // $height = $uploadedVideo->getHeight();

        // try {
        //     $ffmpeg = FFMpeg::create();
        //     $video = $ffmpeg->open($uploadedFile->getRealPath());
        //     $frame = $video->frame(TimeCode::fromSeconds(0));
        //     $thumbnailPath = storage_path('app/public/thumbnails/' . $publicId . '.jpg');
        //     // if (!file_exists(dirname($thumbnailPath))) {
        //     //     mkdir(dirname($thumbnailPath), 0755, true);
        //     // }
        //     Storage::makeDirectory('public/thumbnails');
        //     $frame->save($thumbnailPath);

        //     $uploadedThumbnail = Cloudinary::upload($thumbnailPath, [
        //         'public_id' => $publicId . '_thumbnail',
        //         'resource_type' => 'image',
        //     ]);
        //     $thumbnailUrl = $uploadedThumbnail->getSecurePath();

        //     if (file_exists($thumbnailPath)) {
        //         unlink($thumbnailPath);
        //     }

        //     Post::create([
        //         'user_id'            => $user_id,
        //         'caption'            => $request->input('caption'),
        //         'original_file_name' => $originalFileName,
        //         'file_url'           => $videoUrl,
        //         'thumbnail_url'      => $thumbnailUrl,
        //         'public_id'          => $publicId,
        //         'file_size'          => $fileSize,
        //         'file_type'          => $fileType,
        //         'mime_type'          => $uploadedFile->getMimeType(),
        //         'width'              => $width,
        //         'height'             => $height,
        //     ]);

        //     $user = UserProfile::find($user_id);
        //     if ($user) {
        //         $user->increment('post_count');
        //         $userProfile = $user->profile;
        //         if ($userProfile) {
        //             $userProfile->increment('post_count');
        //         }
        //     }
        // } catch (\Exception $e) {
        //     return response()->json(['error' => 'Could not generate thumbnail: ' . $e->getMessage()], 500);
        // }
    }

    public function addComment(Post $post, $comment)
    {
        $user = Auth::user();
        DB::beginTransaction();
        try {
            $comment = PostComment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'comment' => $comment
            ]);
            $post->update(['comment_count' => $post->comments()->count()]);
            event(new CommentNotification($user, $post, $comment));
            DB::commit();
            return $comment;
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => $exception->getMessage()
            ]);
        }
    }

    public function addReply(Post $post, PostComment $comment, $reply)
    {
        $user = Auth::user();
        DB::beginTransaction();
        try {
            $reply = PostComment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'super_comment_id' => $comment->id,
                'comment' => $reply
            ]);
            $comment->update(['comment_reply_count' => $comment->replies()->count()]);
            event(new CommentReplyNotification($user, $post, $comment, $reply));
            DB::commit();
            return $reply;
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => $exception->getMessage()
            ]);
        }
    }

    public function likePost(Post $post)
    {
        $user = Auth::user();
        DB::beginTransaction();
        try {
            $like = $user->likes()->where('post_id', $post->id)->first();
            if ($like) {
                $like->delete();
                $message = 'Post unliked successfully.';
            } else {
                $user->likes()->create(['post_id' => $post->id]);
                $message = 'Post liked successfully.';
                // Dispatch the LikeNotification event
                event(new LikeNotification($user, $post));
            }
            $post->update(['like_count' => $post->likes()->count()]);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => $exception->getMessage()
            ]);
        }
        return $message;
    }

    public function likePostComment(PostComment $postComment)
    {
        $user = Auth::user();
        DB::beginTransaction();
        try {
            $like = $user->commentLikes()->where('comment_id', $postComment->id)->first();
            $like ? $like->delete() : $user->commentLikes()->create(['comment_id' => $postComment->id]);
            $postComment->update(['comment_like_count' => $postComment->likes()->count()]);
            DB::commit();
            return $like ? 'Post Comment unliked successfully.' : 'Post Comment liked successfully.';
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => $exception->getMessage()
            ]);
        }
    }
}
