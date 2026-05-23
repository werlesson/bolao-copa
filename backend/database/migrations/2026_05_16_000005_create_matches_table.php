<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('external_id')->unique();
            $table->string('home_team')->nullable();
            $table->string('away_team')->nullable();
            $table->string('home_flag', 10)->nullable();
            $table->string('away_flag', 10)->nullable();
            $table->timestamp('starts_at');
            $table->string('stage', 50);
            $table->string('group_name', 50)->nullable();
            $table->string('status', 20)->default('SCHEDULED');
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
