<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('is_admin');
        });

        Schema::table('group_bans', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
        });

        Schema::table('group_bans', function (Blueprint $table) {
            $table->uuid('banned_by')->nullable()->change();
            $table->foreign('banned_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_bans', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
        });

        Schema::table('group_bans', function (Blueprint $table) {
            $table->uuid('banned_by')->nullable(false)->change();
            $table->foreign('banned_by')->references('id')->on('users');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
