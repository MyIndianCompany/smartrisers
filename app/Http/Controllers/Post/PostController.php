<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserProfile;
use App\Services\Posts\PostServices;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    protected PostServices $postServices;

    public function __construct(PostServices $postServices)
    {
        $this->postServices = $postServices;
    }

    public function index()
    {
        $posts = $this->postServices->getPostsQuery()->inRandomOrder()->get();
        return response()->json($posts, 201);
    }

    public function show($id)
    {
        $post = Post::find($id);
        if (!$post) {
            return response()->json(['message' => 'Post not found'], 404);
        }
        $getPost = $this->postServices->getPostsQuery()->where('id', $post->id)->first();
        return response()->json($getPost);
    }

    public function getPostsByUsername($username): \Illuminate\Http\JsonResponse
    {
        $authUserId = auth()->id();
        $user = User::where('username', $username)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the authenticated user has blocked this user
        $isBlocked = DB::table('user_blocks')
            ->where('blocker_id', $authUserId)
            ->where('blocked_id', $user->id)
            ->exists();

        if ($isBlocked) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $posts = $this->postServices->getPostsQuery()->where('user_id', $user->id)->get();
        return response()->json($posts, 201);
    }

    public function getPostsByAuthUsers(): \Illuminate\Http\JsonResponse
    {
        $authUserId = Auth::id();
        $blockedUserIds = UserBlock::where('blocker_id', $authUserId)->pluck('blocked_id')->toArray();
        $blockedByUserIds = UserBlock::where('blocked_id', $authUserId)->pluck('blocker_id')->toArray();
        $excludedUserIds = array_merge($blockedUserIds, $blockedByUserIds);
        $followedUserIds = Follower::where('follower_user_id', $authUserId)->pluck('following_user_id')->toArray();

        $posts = $this->postServices->getPostsQuery($authUserId)->whereNotIn('user_id', $excludedUserIds)->get();

        $posts->each(function ($post) use ($authUserId, $followedUserIds) {
            $post->comment_count = $post->comments->count();
            $post->reply_count = $post->comments->flatMap->replies->count();
            $post->nested_reply_count = $post->comments->flatMap->replies->flatMap->replies->count();
            $post->liked = $post->likes->contains('user_id', $authUserId);
            $post->followed = in_array($post->user->id, $followedUserIds);
            $post->is_owner = $post->user->id === $authUserId;

            $post->comments->each(function ($comment) use ($authUserId) {
                $comment->liked = $authUserId ? $comment->likes->contains('user_id', $authUserId) : false;
    
                $comment->replies->each(function ($reply) use ($authUserId) {
                    $reply->liked = $authUserId ? $reply->likes->contains('user_id', $authUserId) : false;
    
                    $reply->replies->each(function ($nestedReply) use ($authUserId) {
                        $nestedReply->liked = $authUserId ? $nestedReply->likes->contains('user_id', $authUserId) : false;
                    });
                });
            });

            unset($post->likes);
        });
        return response()->json($posts, 201);
    }

    public function getPosts(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $userId = $request->input('user_id', $user->id);
        $posts = $this
            ->postServices
            ->getPostsQuery()
            ->where('user_id', $userId)
            ->get();
        return response()->json($posts, 201);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:mp4,mov,ogg,qt',
            'caption' => 'string|max:255',
        ]);
        try {
            DB::beginTransaction();
            $user_id = auth()->id();
            $uploadedFile = $request->file('file');
            $originalFileName = $uploadedFile->getClientOriginalName();
            $extension = $uploadedFile->getClientOriginalExtension();
            $user = User::find($user_id);
            $username = $user->username;
            $post = Post::create([
                'user_id' => $user_id,
                'caption' => $request->input('caption'),
                'original_file_name' => $originalFileName,
                'file_url' => null,
                'thumbnail_url' => null,
            ]);

            $postId = $post->id;
            $videoPath = "public/{$username}/post/{$postId}/video/{$originalFileName}";
            Storage::put($videoPath, file_get_contents($uploadedFile->getRealPath()));
            // $videoUrl = config('app.url') . Storage::url($videoPath);

            // Force HTTPS for video URL
            $videoUrl = 'https://' . parse_url(config('app.url'), PHP_URL_HOST) . Storage::url($videoPath);

            $thumbnailPath = "public/{$username}/post/{$postId}/video/thumbnail/{$postId}.jpg";
            $thumbnailFullPath = storage_path('app/' . $thumbnailPath);

            $thumbnailDirectory = dirname($thumbnailFullPath);
            if (!is_dir($thumbnailDirectory)) {
                mkdir($thumbnailDirectory, 0755, true);
            }

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
                // 'ffmpeg.binaries'  => 'C:/ffmpeg/bin/ffmpeg.exe',
                // 'ffprobe.binaries' => 'C:/ffmpeg/bin/ffprobe.exe',
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);


            $video = $ffmpeg->open($uploadedFile->getRealPath());

            $video->frame(TimeCode::fromSeconds(1))->save($thumbnailFullPath);

            // $thumbnailUrl = config('app.url') . Storage::url($thumbnailPath);
            // Force HTTPS for thumbnail URL
            $thumbnailUrl = 'https://' . parse_url(config('app.url'), PHP_URL_HOST) . Storage::url($thumbnailPath);

            $post->update([
                'file_url' => $videoUrl,
                'thumbnail_url' => $thumbnailUrl,
                'file_size' => $uploadedFile->getSize(),
                'file_type' => $extension,
                'mime_type' => $uploadedFile->getMimeType(),
            ]);
            $userProfile = UserProfile::find($user_id);
            if ($userProfile) {
                $userProfile->increment('post_count');
            }
            DB::commit();
            return response()->json(['success' => 'Your post has been successfully uploaded!'], 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Failed to process the transaction. Please try again later.',
                'error' => $exception->getMessage()
            ], 422);
        }
    }

    public function destroy(Post $post): \Illuminate\Http\JsonResponse
    {
        try {
            $user = auth()->user();

            // Check authorization
            if ($post->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json(['message' => 'You are not authorized to delete this post.'], 403);
            }

            // Get the username and paths
            $username = User::find($post->user_id)->username;
            $postId = $post->id;

            // Paths to the video file and thumbnail
            $videoPath = "public/{$username}/post/{$postId}/video/{$post->original_file_name}"; // Video file path
            $thumbnailPath = "public/{$username}/post/{$postId}/video/thumbnail/{$postId}.jpg"; // Thumbnail path

            // Delete video if exists
            if (Storage::exists($videoPath)) {
                Storage::delete($videoPath);
            }

            // Delete thumbnail if exists
            if (Storage::exists($thumbnailPath)) {
                Storage::delete($thumbnailPath);
            }

            // Delete the post record from the database
            $post->delete();

            // Decrement post count in user profile
            $userProfile = UserProfile::find($post->user_id);
            if ($userProfile && $userProfile->post_count > 0) {
                $userProfile->decrement('post_count');
            }

            // Check if both video and thumbnail are deleted and delete the postId directory
            $postDirectory = "public/{$username}/post/{$postId}";
            if (!Storage::exists($videoPath) && !Storage::exists($thumbnailPath) && Storage::exists($postDirectory)) {
                Storage::deleteDirectory($postDirectory);
            }

            // Check if there are any other posts for this user
            $remainingPosts = Post::where('user_id', $post->user_id)->count();

            // If no posts remain, delete the entire user folder (username folder)
            $userDirectory = "public/{$username}";
            if ($remainingPosts == 0 && Storage::exists($userDirectory)) {
                Storage::deleteDirectory($userDirectory);
            }

            return response()->json(['success' => 'Post and associated media deleted successfully.'], 200);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to delete the post. Please try again later.',
                'error' => $exception->getMessage()
            ], 422);
        }
    }



    public function like(Post $post): \Illuminate\Http\JsonResponse
    {
        try {
            $message = $this->postServices->likePost($post);
            return response()->json([
                'total_likes' => $post->likes()->count(),
                'message' => $message
            ], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to like/unlike the post. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }
}
