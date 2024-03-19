<?php

namespace App\Http\Controllers;

use App\Common\Constant\Constants;
use App\Exceptions\CustomException\BbyteException;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentReply;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::inRandomOrder()->get();

        $posts->load([
            'comments' => function ($query) {
                $query->whereNull('super_comment_id');
            },
            'comments.user' => function ($query) {
                $query->select('id', 'name', 'username');
            },
            'comments.replies.user' => function ($query) {
                $query->select('id', 'name', 'username');
            },
            'comments.replies.replies.user' => function ($query) {
                $query->select('id', 'name', 'username');
            },
            'comments.replies.replies.replies.user' => function ($query) {
                $query->select('id', 'name', 'username');
            }
        ]);

        $posts->each(function ($post) {
            $post->comment_count = $post->comments->count();
            $post->reply_count = $post->comments->flatMap->replies->count();
            $post->nested_reply_count = $post->comments->flatMap->replies->flatMap->replies->count();
        });

        return response()->json(['posts' => $posts], 201);
    }
    public function getPostsByUserId(Request $request)
    {
        $user_id = $request->input('user_id');
        $posts = Post::where('user_id', $user_id)->inRandomOrder()
            ->with([
                'comments' => function ($query) {
                    $query->whereNull('super_comment_id');
                },
                'comments.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                },
                'comments.replies.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                },
                'comments.replies.replies.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                },
                'comments.replies.replies.replies.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                }
            ])
            ->get();

        $posts->each(function ($post) {
            $post->comment_count = $post->comments->count();
            $post->reply_count = $post->comments->flatMap->replies->count();
            $post->nested_reply_count = $post->comments->flatMap->replies->flatMap->replies->count();
            if (auth()) {
                $post->liked = $post->likes->contains('user_id', auth()->id());
            }
            unset($post->likes); // Remove the likes array from the post object
        });

        return response()->json(['posts' => $posts], 201);
    }

    public function getPostsByAuthUsers(Request $request)
    {
        $posts = Post::inRandomOrder()
            ->with([
                'comments' => function ($query) {
                    $query->whereNull('super_comment_id');
                },
                'comments.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                },
                'comments.replies.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                },
                'comments.replies.replies.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                },
                'comments.replies.replies.replies.user' => function ($query) {
                    $query->select('id', 'name', 'username');
                }
            ])
            ->get();

        $posts->each(function ($post) {
            $post->comment_count = $post->comments->count();
            $post->reply_count = $post->comments->flatMap->replies->count();
            $post->nested_reply_count = $post->comments->flatMap->replies->flatMap->replies->count();
            $post->liked = $post->likes->contains('user_id', auth()->id());
            unset($post->likes); // Remove the likes array from the post object
        });

        return response()->json(['posts' => $posts], 201);
    }

    public function getPosts(Request $request)
    {
        $user = auth()->user();
        $userId = $request->input('user_id', $user->id);
        $posts = Post::where('user_id', $userId)->inRandomOrder()->get();
        $user->load('likes');

        $posts->load([
            'comments' => function ($query) {
                $query->whereNull('super_comment_id');
            },
            'comments.user' => function ($query) {
                $query->select('id', 'name', 'username');
            },
            'comments.replies.user' => function ($query) {
                $query->select('id', 'name', 'username');
            },
            'comments.replies.replies.user' => function ($query) {
                $query->select('id', 'name', 'username');
            },
            'comments.replies.replies.replies.user' => function ($query) {
                $query->select('id', 'name', 'username');
            }
        ]);

        $posts->each(function ($post) {
            $post->comment_count = $post->comments->count();
            $post->reply_count = $post->comments->flatMap->replies->count();
            $post->nested_reply_count = $post->comments->flatMap->replies->flatMap->replies->count();
        });

        $posts->each(function ($post) use ($user) {
            $post->liked = $user->likes->contains('post_id', $post->id);
        });

        return response()->json(['posts' => $posts], 201);
    }



    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:mp4,mov,avi|max:102400',
        ]);
        $uploadedFile = $request->file('file');
        try {
            DB::beginTransaction();
            $originalFileName = $uploadedFile->getClientOriginalName();
            $uploadedVideo    = Cloudinary::uploadVideo($uploadedFile->getRealPath());
            $videoUrl         = $uploadedVideo->getSecurePath();
            $publicId         = $uploadedVideo->getPublicId();
            $fileSize         = $uploadedVideo->getSize();
            $fileType         = $uploadedVideo->getFileType();
            $width            = $uploadedVideo->getWidth();
            $height           = $uploadedVideo->getHeight();
            if (!$uploadedFile) {
                throw new BbyteException('File not found!');
            }
            $user = auth()->user()->id;
            Post::create([
                'user_id'            => $user,
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
    public function like(Post $post)
    {
        try {
            DB::beginTransaction();
            $user = auth()->user();
            $like = $user->likes()->where('post_id', $post->id)->first();
            $like ? $like->delete() : $user->likes()->create(['post_id' => $post->id]);
            $post->update(['like_count' => $post->likes()->count()]);
            DB::commit();
            return response()->json(['message' => 'Post liked successfully.'], 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Failed to like the post. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    public function comment(Request $request, Post $post)
    {
        $request->validate([
            'comment' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();
            $user = auth()->user();
            PostComment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'comment' => $request->input('comment')
            ]);
            $post->update(['comment_count' => $post->comments()->count()]);
            DB::commit();
            return response()->json(['message' => 'Comment added successfully.'], 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Failed to add comment. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }

    //Comment Reply
    public function reply(Request $request, Post $post, PostComment $comment)
    {
        $request->validate([
            'comment' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();
            $user = auth()->user();
            $reply = PostComment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'super_comment_id' => $comment->id,
                'comment' => $request->input('comment')
            ]);
            DB::commit();
            return response()->json(['message' => 'Comment reply added successfully.', 'reply' => $reply], 201);
        } catch (\Exception $exception) {
            DB::rollBack();
            report($exception);
            return response()->json([
                'message' => 'Failed to add comment reply. Please try again later.',
                'error' => $exception->getMessage()
            ], 401);
        }
    }
}
