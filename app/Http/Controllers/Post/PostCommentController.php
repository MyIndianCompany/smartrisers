<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\Posts\PostServices;

class PostCommentController extends Controller
{
    private PostServices $postServices;

    public function __construct(PostServices $postServices)
    {
        $this->postServices = $postServices;
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

    public function commentLike(Request $request, PostComment $postComment): \Illuminate\Http\JsonResponse
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

    public function deleteComment(PostComment $comment): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if ($user->id !== $comment->user_id && $user->id !== $comment->post->user_id) {
            return response()->json(['message' => 'Unauthorized to delete this comment.'], 403);
        }
        DB::beginTransaction();
        try {
            $comment->replies()->delete();
            $comment->delete();
            DB::commit();
            return response()->json(['message' => 'Comment and its replies deleted successfully.'], 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete comment and its replies.',
                'error' => $exception->getMessage()
            ], 500);
        }
    }
}
