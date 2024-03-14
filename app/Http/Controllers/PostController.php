<?php

namespace App\Http\Controllers;

use App\Common\Constant\Constants;
use App\Exceptions\CustomException\BbyteException;
use App\Models\Post;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Post::inRandomOrder()->get();
    }

    public function getPosts(Request $request)
    {
        $user = auth()->user();
        $userId = $request->input('user_id', $user->id);
        $posts = Post::where('user_id', $userId)->inRandomOrder()->get();
        $user->load('likes');
        $posts->each(function ($post) use ($user) {
            $post->liked = $user->likes->contains('post_id', $post->id);
        });
        return response()->json(['posts' => $posts], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
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
}
