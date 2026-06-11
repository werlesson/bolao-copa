<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'            => fake()->name(),
            'email'           => fake()->unique()->safeEmail(),
            'google_id'       => (string) fake()->unique()->numerify('##########'),
            'avatar_url'      => null,
            'onboarding_done' => true,
            'is_admin'        => false,
        ];
    }
}
