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
        Schema::table('content_projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->nullable()->default(2)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_projects', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
