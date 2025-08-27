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
            // add role_id if it doesn't exist
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->constrained('roles');
            }

            // add role_name if it doesn't exist
            if (!Schema::hasColumn('users', 'role_name')) {
                $table->string('role_name')->nullable()->after('role_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('role_name');
        });
    }
};
