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
        Schema::create('user_report_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_report_id');
            $table->string('original_file_name');
            $table->string('files');
            $table->string('mime_type');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('user_report_id')->references('id')->on('user_reports');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_report_files');
    }
};
