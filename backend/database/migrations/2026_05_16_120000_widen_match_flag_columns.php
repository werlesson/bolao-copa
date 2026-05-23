<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matches')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE matches ALTER COLUMN home_flag TYPE VARCHAR(512)');
            DB::statement('ALTER TABLE matches ALTER COLUMN away_flag TYPE VARCHAR(512)');
        } else {
            DB::statement('ALTER TABLE matches MODIFY home_flag VARCHAR(512) NULL');
            DB::statement('ALTER TABLE matches MODIFY away_flag VARCHAR(512) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('matches')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE matches ALTER COLUMN home_flag TYPE VARCHAR(10)');
            DB::statement('ALTER TABLE matches ALTER COLUMN away_flag TYPE VARCHAR(10)');
        } else {
            DB::statement('ALTER TABLE matches MODIFY home_flag VARCHAR(10) NULL');
            DB::statement('ALTER TABLE matches MODIFY away_flag VARCHAR(10) NULL');
        }
    }
};
