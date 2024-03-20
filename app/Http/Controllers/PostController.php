<?php

namespace App\Http\Controllers;

use App\Models\Follower;
use App\Models\Post;
use App\Models\PostComment;
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
        $posts = $this->postServices->getPostsQuery()->get();
        return response()->json($posts, 201);
    }

    public function getPostsByUserId($userId): \Illuminate\Http\JsonResponse
    {
        $posts = $this
            ->postServices
            ->getPostsQuery()
            ->where('user_id', $userId)
            ->get();
        return response()->json($posts, 201);
    }

    public function getPostsByAuthUsers(): \Illuminate\Http\JsonResponse
    {
        $authUserId = Auth::id();
        $followedUserIds = Follower::where('follower_user_id', $authUserId)
            ->pluck('following_user_id')
            ->toArray();
        $posts = $this->postServices->getPostsQuery()->get();
        $posts->each(function ($post) use ($authUserId, $followedUserIds) {
            $post->comment_count = $post->comments->count();
            $post->reply_count = $post->comments->flatMap->replies->count();
            $post->nested_reply_count = $post->comments->flatMap->replies->flatMap->replies->count();
            $post->liked = $post->likes->contains('user_id', $authUserId);
            $post->followed = in_array($post->user->id, $followedUserIds);
            $post->is_owner = $post->user->id === $authUserId;
            unset ($post->likes);
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
            'file' => 'required|mimes:mp4,mov,avi|max:102400',
        ]);
        try {
            DB::beginTransaction();
            $this->postServices->uploadPost($request);
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

    public function comment(Request $request, Post $post): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'comment' => 'required|string|max:255',
        ]);
        try {
            $this->postServices->addComment($post, $request->input('comment'));
            return response()->json(['message' => 'Comment added successfully.'], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to add comment. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function reply(Request $request, Post $post, PostComment $comment): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'comment' => 'required|string|max:255',
        ]);
        try {
            $this->postServices->addReply($post, $comment, $request->input('comment'));
            return response()->json(['message' => 'Comment reply added successfully.'], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to add comment reply. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function like(Post $post): \Illuminate\Http\JsonResponse
    {
        try {
            $message = $this->postServices->likePost($post);
            return response()->json(['message' => $message], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to like/unlike the post. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function commentLike(PostComment $postComment): \Illuminate\Http\JsonResponse
    {
        try {
            $message = $this->postServices->likePostComment($postComment);
            return response()->json(['message' => $message], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to like/unlike the post comment. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }
}
