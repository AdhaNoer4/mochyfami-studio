<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This column is the selection, and it is deliberately kept out of the
     * asset_requirement_asset pivot. The pivot records candidacy and this
     * records a decision about one of those candidates, so they are different
     * facts about the requirement: a requirement may have many candidates and
     * at most one selection, which is a shape a pivot with a boolean could only
     * half express and could not enforce.
     *
     * The foreign key nulls rather than cascades. Deleting the selected asset
     * must not delete a requirement that also holds a description, a target
     * duration, and a position in the plan; the requirement outlives the file
     * that satisfied it. Nulling leaves the requirement without a selection,
     * which AssetService answers by reverting it out of fulfilled so the row
     * never claims a fulfillment it no longer has.
     *
     * Nothing here asserts that a selected asset is also a candidate, and that
     * is on purpose too: the constraint spans two tables through the pivot and
     * the service is where both tables are already in hand.
     */
    public function up(): void
    {
        Schema::table('asset_requirements', function (Blueprint $table) {
            $table->foreignId('selected_asset_id')
                ->nullable()
                ->after('aspect_ratio')
                ->constrained('assets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_asset_id');
        });
    }
};
