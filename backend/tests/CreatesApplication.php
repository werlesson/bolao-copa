<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        // PHPUnit 12 no Docker não aplica <env> do phpunit.xml de forma confiável.
        // Usar bolao_test para RefreshDatabase não apagar o banco de desenvolvimento (bolao).
        $dbHost = file_exists('/.dockerenv') ? 'postgres' : '127.0.0.1';

        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=pgsql');
        putenv('DB_DATABASE=bolao_test');
        putenv("DB_HOST={$dbHost}");
        putenv('DB_PORT=5432');
        putenv('DB_USERNAME=bolao');
        putenv('DB_PASSWORD=secret');
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'pgsql';
        $_ENV['DB_DATABASE'] = 'bolao_test';
        $_ENV['DB_HOST'] = $dbHost;
        $_ENV['DB_PORT'] = '5432';
        $_ENV['DB_USERNAME'] = 'bolao';
        $_ENV['DB_PASSWORD'] = 'secret';
        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['DB_CONNECTION'] = 'pgsql';
        $_SERVER['DB_DATABASE'] = 'bolao_test';
        $_SERVER['DB_HOST'] = $dbHost;
        $_SERVER['DB_PORT'] = '5432';
        $_SERVER['DB_USERNAME'] = 'bolao';
        $_SERVER['DB_PASSWORD'] = 'secret';

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
