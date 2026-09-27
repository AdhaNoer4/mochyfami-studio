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
        Schema::create('script_version_research_claim', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_version_id')->constrained('script_versions')->cascadeOnDelete();
            $table->foreignId('research_claim_id')->constrained('research_claims')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['script_version_id', 'research_claim_id'], 'svrc_claim_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('script_version_research_claim');
    }
};
