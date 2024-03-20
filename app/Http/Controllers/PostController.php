<?php

namespace App\Http\Controllers;

use App\Common\Constant\Constants;
use App\Exceptions\CustomException\BbyteException;
use App\Models\Follower;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\Posts\PostServices;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    protected $postService;

    public function __construct(PostServices $postService)
    {
        $this->postService = $postService;
    }

    public function index()
    {
        $posts = $this->postService->getPostsQuery()->get();
        return response()->json($posts, 201);
    }

    public function getPostsByUserId(Request $request, $userId)
    {
        $posts = $this->postService->getPostsQuery()
            ->where('user_id', $userId)
            ->get();
        return response()->json($posts, 201);
    }

    public function getPostsByAuthUsers(Request $request)
    {
        $authUserId = Auth::id();
        $followedUserIds = Follower::where('follower_user_id', $authUserId)
            ->pluck('following_user_id')
            ->toArray();

        $posts = $this->postService->getPostsQuery()->get();
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

    public function getPosts(Request $request)
    {
        $user = Auth::user();
        $userId = $request->input('user_id', $user->id);
        $posts = $this->postService->getPostsQuery()
            ->where('user_id', $userId)
            ->get();
        return response()->json($posts, 201);
    }

    public function store(Request $request, PostServices $postService)
    {
        $request->validate([
            'file' => 'required|mimes:mp4,mov,avi|max:102400',
        ]);
        try {
            DB::beginTransaction();
            $postService->uploadPost($request);
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

    public function destroy(Post $post)
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

    public function comment(Request $request, Post $post, PostServices $postService)
    {
        $request->validate([
            'comment' => 'required|string|max:255',
        ]);

        try {
            $comment = $postService->addComment($post, $request->input('comment'));
            return response()->json(['message' => 'Comment added successfully.', 'comment' => $comment], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to add comment. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function reply(Request $request, Post $post, PostComment $comment, PostServices $postService)
    {
        $request->validate([
            'comment' => 'required|string|max:255',
        ]);

        try {
            $reply = $postService->addReply($post, $comment, $request->input('comment'));
            return response()->json(['message' => 'Comment reply added successfully.', 'reply' => $reply], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to add comment reply. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function like(Post $post, PostServices $postService)
    {
        try {
            $message = $postService->likePost($post);
            return response()->json(['message' => $message], 201);
        } catch (\Exception $exception) {
            report($exception);
            return response()->json([
                'message' => 'Failed to like/unlike the post. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function commentLike(PostComment $postComment, PostServices $postService)
    {
        try {
            $message = $postService->likePostComment($postComment);
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
