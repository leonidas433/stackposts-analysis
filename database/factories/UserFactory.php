<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AdminUsers\Models\Teams;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_secure' => Str::random(10),
            'role' => 1,
            'login_type' => 'direct',
            'fullname' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'plan_id' => 1,
            'timezone' => fake()->randomElement(timezone_identifiers_list()),
            'status' => 2,
            'remember_token' => Str::random(100),
            'changed' => time(),
            'created' => time(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            if (! Teams::where('owner', $user->id)->exists()) {
                Teams::create([
                    'id_secure' => Str::random(10),
                    'owner' => $user->id,
                    'name' => 'Personal',
                    'permissions' => [],
                    'data' => [],
                ]);
            }
        });
    }
}
