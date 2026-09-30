<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every column here is metadata about a media file. Nothing in this
     * migration, and nothing that reads it, touches the filesystem.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_project_id')->constrained('content_projects')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('status', 20)->default('pending');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_name')->nullable();
            $table->string('license_type', 100)->nullable();
            $table->string('attribution')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['content_project_id', 'status']);
            $table->index(['content_project_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
