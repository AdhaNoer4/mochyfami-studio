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
        Schema::create('visual_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('script_version_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['content_project_id', 'script_version_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visual_plans');
    }
};
