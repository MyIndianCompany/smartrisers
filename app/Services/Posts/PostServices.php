<?php

namespace App\Services\Posts;

use App\Events\CommentNotification;
use App\Events\CommentReplyNotification;
use App\Events\LikeNotification;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\UserProfile;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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
                'public_id',
                'like_count',
                'comment_count',
                'created_at'
            );
    }

    public function uploadPost(Request $request, int $user_id)
    {
        // Validate the input fields
        $request->validate([
            'file' => 'required|file|mimes:mp4,mov,ogg,qt',
            'caption' => 'required|string|max:255',
        ]);

        // Retrieve the uploaded file
        $uploadedFile = $request->file('file');
        $originalFileName = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension();

        // Fetch the username and create folder path structure
        $user = User::find($user_id);
        $username = $user->username; // Assuming the user has a 'username' field

        // Create post object to get the post_id
        $post = Post::create([
            'user_id' => $user_id,
            'caption' => $request->input('caption'),
            'original_file_name' => $originalFileName,
            // File and thumbnail URLs will be updated later
        ]);

        $postId = $post->id;

        // Define paths for video and thumbnail
        $videoPath = "public/{$username}/post/{$postId}/video/{$originalFileName}";
        $thumbnailPath = "public/{$username}/post/{$postId}/thumbnail/" . pathinfo($originalFileName, PATHINFO_FILENAME) . ".jpg";

        // Store the uploaded video in the defined path
        Storage::put($videoPath, file_get_contents($uploadedFile->getRealPath()));

        // Generate thumbnail using FFMpeg
        try {
            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open($uploadedFile->getRealPath());
            $frame = $video->frame(TimeCode::fromSeconds(0));

            // Ensure the thumbnail directory exists
            if (!Storage::exists(dirname($thumbnailPath))) {
                Storage::makeDirectory(dirname($thumbnailPath), 0755, true);
            }

            // Save the thumbnail
            $thumbnailFullPath = storage_path("app/{$thumbnailPath}");
            $frame->save($thumbnailFullPath);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Could not generate thumbnail: ' . $e->getMessage()], 500);
        }

        // Store file URL and thumbnail URL
        $videoUrl = Storage::url($videoPath);
        $thumbnailUrl = Storage::url($thumbnailPath);

        // Update the post record with the correct paths
        $post->update([
            'file_url' => $videoUrl,
            'thumbnail_url' => $thumbnailUrl,
            'file_size' => $uploadedFile->getSize(),
            'file_type' => $extension,
            'mime_type' => $uploadedFile->getMimeType(),
            'width' => $video->getStreams()->videos()->first()->get('width'),
            'height' => $video->getStreams()->videos()->first()->get('height'),
        ]);

        // Increment the user's post count
        $userProfile = UserProfile::find($user_id);
        if ($userProfile) {
            $userProfile->increment('post_count');
        }

        return response()->json(['success' => 'Your post has been successfully uploaded!'], 201);
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
