<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rankings', function (Blueprint $table) {
            $table->unsignedInteger('last_position')->nullable()->after('total_predictions');
        });
    }

    public function down(): void
    {
        Schema::table('rankings', function (Blueprint $table) {
            $table->dropColumn('last_position');
        });
    }
};
