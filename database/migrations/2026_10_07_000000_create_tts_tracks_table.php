<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A tts_tracks row is a history entry: every synthesis is recorded and
     * regenerating never replaces an older track. The narration that was
     * actually given to the provider is snapshotted alongside a SHA-256 of it,
     * so readiness can later detect that the plan text moved on since a track
     * was synthesized. asset_id is nullable because a failed generation has no
     * audio file yet.
     */
    public function up(): void
    {
        Schema::create('tts_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_project_id')->constrained('content_projects')->cascadeOnDelete();
            $table->foreignId('visual_plan_item_id')->constrained('visual_plan_items')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('model')->nullable();
            $table->string('voice', 100);
            $table->string('language', 20)->nullable();
            $table->decimal('speed', 5, 3)->default(1.000);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('narration_snapshot');
            $table->char('narration_hash', 64);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['content_project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tts_tracks');
    }
};
