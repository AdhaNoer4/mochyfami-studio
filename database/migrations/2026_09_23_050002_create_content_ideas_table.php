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
        Schema::create('content_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('hook')->nullable();
            $table->text('concept')->nullable();
            $table->string('format', 50)->default('educational');
            $table->string('status', 50)->default('idea');
            $table->string('priority', 20)->default('medium');
            $table->text('notes')->nullable();
            $table->string('source_idea')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'category_id']);
            $table->index('format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_ideas');
    }
};
