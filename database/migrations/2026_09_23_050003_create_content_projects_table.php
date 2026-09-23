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
        Schema::create('content_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_idea_id')->nullable()->constrained('content_ideas')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status', 50)->default('draft');
            $table->unsignedInteger('target_duration_seconds')->default(30);
            $table->string('language', 10)->default('id');
            $table->string('tone', 50)->default('casual');
            $table->text('hook')->nullable();
            $table->text('description')->nullable();
            $table->string('current_step', 50)->default('draft');
            $table->unsignedSmallInteger('progress_percent')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'category_id']);
            $table->index('content_idea_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_projects');
    }
};
