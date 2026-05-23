<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->integer('home_score');
            $table->integer('away_score');
            $table->integer('points_earned')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
