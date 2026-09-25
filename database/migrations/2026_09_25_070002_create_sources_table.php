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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_report_id')->constrained('research_reports')->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 2048);
            $table->string('domain')->nullable();
            $table->string('source_type', 50)->default('other');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
