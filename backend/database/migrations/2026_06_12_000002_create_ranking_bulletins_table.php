<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_bulletins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->text('content');
            $table->string('source', 20);
            $table->json('movement_summary')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['group_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_bulletins');
    }
};
