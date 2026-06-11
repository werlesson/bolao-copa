<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_uses_isolated_database_not_dev_postgres(): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        $this->assertSame('bolao_test', $database);
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertNotSame('bolao', $database, 'Testes não podem usar o banco de desenvolvimento.');
    }
}
