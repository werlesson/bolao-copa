<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GlobalGroupSeeder extends Seeder
{
    public function run(): void
    {
        Group::firstOrCreate(
            ['is_global' => true],
            [
                'name'             => 'Geral Copa 2026',
                'slug'             => 'geral-copa-2026',
                'invite_token'     => Str::random(32),
                'owner_id'         => null,
                'require_approval' => false,
                'max_members'      => null,
            ]
        );
    }
}
