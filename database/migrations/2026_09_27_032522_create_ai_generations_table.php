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
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('model', 100)->nullable();
            $table->string('prompt_profile', 50)->nullable();
            $table->string('prompt_version', 20)->nullable();
            $table->foreignId('source_version_id')->nullable()->constrained('script_versions')->nullOnDelete();
            $table->foreignId('generated_version_id')->nullable()->constrained('script_versions')->nullOnDelete();
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
