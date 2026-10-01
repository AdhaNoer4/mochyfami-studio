<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Technical metadata extracted by inspection, plus the state of that
     * inspection.
     *
     * width and height already exist from Part 1 and are not repeated here.
     * duration_seconds already exists as an unsigned integer and is left
     * alone: sub-second precision would need a decimal, but an integer still
     * answers "is this longer than the shot" for every comparison this phase
     * makes, and widening it now would rewrite data Part 1 already stores.
     * A later metadata phase can widen it if a real comparison needs the
     * fraction.
     *
     * aspect_ratio is stored numerically rather than as a string like "16:9".
     * The requirement side of this database already keeps a varchar, but that
     * column is a short list of named ratios a human picks; a measured value
     * has no such shortlist, and a numeric ratio is what a later comparison
     * against it would actually compute with. The frontend formats it.
     *
     * decimal(8,4) leaves room for a ratio of 1000:1, which is beyond any
     * plausible frame but keeps an ultrawide capture from being silently
     * truncated by the column rather than refused by the code.
     *
     * The three metadata_* columns exist because a null width cannot mean two
     * different things. An image genuinely has no duration, and a video whose
     * analysis failed has no known dimensions; those are not the same state,
     * and without a status they are indistinguishable from each other and from
     * an asset that was never analysed at all.
     *
     * metadata_status is a nullable string rather than an enum column so that
     * the vocabulary lives in one PHP enum and is read through a cast, which
     * is how AssetStatus and AssetType are already stored.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('aspect_ratio', 8, 4)->nullable()->after('height');
            $table->string('metadata_status', 20)->nullable()->after('aspect_ratio');
            $table->text('metadata_error')->nullable()->after('metadata_status');
            $table->timestamp('metadata_analyzed_at')->nullable()->after('metadata_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'aspect_ratio',
                'metadata_status',
                'metadata_error',
                'metadata_analyzed_at',
            ]);
        });
    }
};
