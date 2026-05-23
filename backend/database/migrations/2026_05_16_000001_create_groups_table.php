<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('invite_token')->unique();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_global')->default(false);
            $table->boolean('require_approval')->default(false);
            $table->integer('max_members')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
