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
            $uploadedVideo = Cloudinary::uploadVideo($uploadedFile->getRealPath());
            $videoUrl = $uploadedVideo->getSecurePath();
            $publicId = $uploadedVideo->getPublicId();
            $fileSize = $uploadedVideo->getSize();
            $fileType = $uploadedVideo->getFileType();
            $width = $uploadedVideo->getWidth();
            $height = $uploadedVideo->getHeight();

            if (!$uploadedFile) {
                throw new BbyteException('File not found!');
            }
            $user = auth()->user()->id;
            Post::create([
                'user_id'            => $user,
                'original_file_name' => $originalFileName,
                'file_url'           => $videoUrl,
                'mime_type'          => $uploadedFile->getMimeType(),
                'caption'            => $request->input('caption')
            ]);
            DB::commit();
            return response()->json([
                'success' => 'Your post has been successfully uploaded!'
            ], 201);
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
        //
    }
}
