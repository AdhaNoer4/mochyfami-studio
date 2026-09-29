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
        Schema::create('asset_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visual_plan_item_id')->constrained()->cascadeOnDelete();
            $table->string('requirement_type', 50);
            $table->string('search_query')->nullable();
            $table->text('description');
            $table->unsignedInteger('target_duration_seconds')->nullable();
            $table->string('aspect_ratio', 10)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requirements');
    }
};
