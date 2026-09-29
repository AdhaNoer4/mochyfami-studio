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
        Schema::create('visual_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visual_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('section', 20);
            $table->text('narration_text');
            $table->string('visual_type', 50);
            $table->text('visual_prompt');
            $table->unsignedInteger('duration_seconds');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['visual_plan_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visual_plan_items');
    }
};
