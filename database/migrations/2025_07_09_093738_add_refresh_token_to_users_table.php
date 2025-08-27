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
        Schema::table('users', function (Blueprint $table) {
            // Only add refresh_token here; role columns are handled in later migrations
            if (!Schema::hasColumn('users', 'refresh_token')) {
                $table->string('refresh_token')->nullable()->after('remember_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'refresh_token')) {
                $table->dropColumn('refresh_token');
            }
        });
    }
};
