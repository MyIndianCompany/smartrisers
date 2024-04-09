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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('username');
            $table->string('email')->unique();
            $table->text('bio')->nullable();
            $table->enum('gender', ['MALE', 'FEMALE', 'CUSTOM'])->default('MALE');
            $table->string('custom_gender')->nullable();
            $table->string('profile_picture')->nullable();
            $table->integer('post_count')->default(0);
            $table->integer('follower_count')->default(0);
            $table->integer('following_count')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
