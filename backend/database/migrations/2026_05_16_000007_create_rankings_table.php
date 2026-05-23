<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rankings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete();
            $table->integer('total_points')->default(0);
            $table->integer('exact_scores')->default(0);
            $table->integer('correct_results')->default(0);
            $table->integer('total_predictions')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rankings');
    }
};
