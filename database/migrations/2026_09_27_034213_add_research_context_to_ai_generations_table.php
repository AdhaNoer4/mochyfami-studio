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
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->bigInteger('script_id')->unsigned()->nullable()->change();
            $table->foreignId('research_report_id')->nullable()->after('script_id')->constrained('research_reports')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('research_report_id');
            $table->bigInteger('script_id')->unsigned()->nullable(false)->change();
        });
    }
};
