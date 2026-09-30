<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The table records one thing only: this asset is a candidate associated
     * with this requirement. It is deliberately not a decision table. There is
     * no selected, fulfilled, primary, or winner column here, because
     * association is not fulfillment and Part 3 decides nothing.
     *
     * Both foreign keys cascade, so deleting an asset or a requirement removes
     * the association and nothing else. Neither side may cascade the other
     * domain entity away: an asset outlives the requirements it was a
     * candidate for, and a requirement outlives any one asset.
     *
     * The unique pair is the last line of defence against a duplicate
     * association. The service rejects a second attach with a 409 before this
     * constraint is ever reached, but the database still has to hold the line
     * against a race between two concurrent requests.
     */
    public function up(): void
    {
        Schema::create('asset_requirement_asset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_requirement_id')->constrained('asset_requirements')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asset_requirement_id', 'asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requirement_asset');
    }
};
