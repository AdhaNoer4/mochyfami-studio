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
        Schema::create('research_claim_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_claim_id')->constrained('research_claims')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['research_claim_id', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_claim_sources');
    }
};
