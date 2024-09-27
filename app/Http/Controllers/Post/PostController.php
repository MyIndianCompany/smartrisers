<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Posts\PostServices;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $posts = $this->postServices->getPostsQuery()->whereNotIn('user_id', $excludedUserIds)->get();
        $posts->each(function ($post) use ($authUserId, $followedUserIds) {
            $post->comment_count = $post->comments->count();
            $post->reply_count = $post->comments->flatMap->replies->count();
            $post->nested_reply_count = $post->comments->flatMap->replies->flatMap->replies->count();
            $post->liked = $post->likes->contains('user_id', $authUserId);
            $post->followed = in_array($post->user->id, $followedUserIds);
            $post->is_owner = $post->user->id === $authUserId;
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

    public function store(Request $request, PostServices $postService): \Illuminate\Http\JsonResponse
    {
        $request->validate(['file' => 'required|mimes:mp4,mov,avi']);
        try {
            DB::beginTransaction();
            $user_id = auth()->id();
            $postService->uploadPost($request, $user_id);
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
            if ($post->user_id !== auth()->user()->id) {
                return response()->json(['message' => 'You are not authorized to delete this post.'], 403);
            }
            Cloudinary::destroy($post->public_id);
            $post->delete();
            return response()->json(['success' => 'Post deleted successfully.'], 201);
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
