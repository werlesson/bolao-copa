<?php

namespace App\Contracts;

use App\Exceptions\BulletinGenerationException;

interface RankingBulletinGenerator
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws BulletinGenerationException
     */
    public function generate(array $payload): string;
}
