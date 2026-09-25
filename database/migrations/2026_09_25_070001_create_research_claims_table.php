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
        Schema::create('research_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_report_id')->constrained('research_reports')->cascadeOnDelete();
            $table->text('claim');
            $table->string('status', 50)->default('unverified');
            $table->string('importance', 20)->default('medium');
            $table->timestamps();

            $table->index(['research_report_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_claims');
    }
};
