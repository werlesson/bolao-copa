<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->index('status', 'matches_status_index');
            $table->index('starts_at', 'matches_starts_at_index');
            $table->index(['status', 'starts_at'], 'matches_status_starts_at_index');
        });

        Schema::table('predictions', function (Blueprint $table) {
            $table->index('match_id', 'predictions_match_id_index');
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->index('user_id', 'group_members_user_id_index');
        });

        Schema::table('rankings', function (Blueprint $table) {
            $table->index(['group_id', 'total_points', 'exact_scores'], 'rankings_group_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_status_index');
            $table->dropIndex('matches_starts_at_index');
            $table->dropIndex('matches_status_starts_at_index');
        });

        Schema::table('predictions', function (Blueprint $table) {
            $table->dropIndex('predictions_match_id_index');
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropIndex('group_members_user_id_index');
        });

        Schema::table('rankings', function (Blueprint $table) {
            $table->dropIndex('rankings_group_order_index');
        });
    }
};
