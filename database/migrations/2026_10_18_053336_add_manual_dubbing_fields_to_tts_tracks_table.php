<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Manual dubbing extends the same tts_tracks history instead of introducing
     * a second table: a track is still one spoken rendition of one visual plan
     * item, whether a provider synthesized it or a human recorded it. source
     * records that origin and defaults to 'tts', so every pre-existing row
     * keeps its meaning without a backfill.
     *
     * approval_status is deliberately separate from the lifecycle status: an
     * automated track is never pending review, and a manual recording can be
     * complete as a file while still awaiting approval. It is nullable with no
     * default so old TTS tracks do not appear to need manual review. Both user
     * foreign keys use nullOnDelete so removing an account never erases the
     * dubbing history that referenced it.
     */
    public function up(): void
    {
        Schema::table('tts_tracks', function (Blueprint $table) {
            $table->string('source', 10)->default('tts');
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->string('approval_status', 20)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tts_tracks', function (Blueprint $table) {
            $table->dropForeign(['uploader_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'source',
                'uploader_id',
                'recorded_at',
                'approval_status',
                'approved_at',
                'approved_by',
                'notes',
            ]);
        });
    }
};
