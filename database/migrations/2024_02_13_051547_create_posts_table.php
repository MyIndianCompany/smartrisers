<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->longText('caption')->nullable();
            $table->string('original_file_name')->nullable();
            $table->string('file_url')->nullable();
            $table->string('public_id')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('width')->nullable();
            $table->string('height')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
