<?php

namespace Database\Factories;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requester = User::factory();
        $addressee = User::factory()->create(['name' => fake()->name()]);

        return [
            'requester_id' => $requester->id,
            'addressee_id' => $addressee->id,
            'status' => fake()->randomElement(['pending', 'accepted', 'rejected']),
        ];
    }

    /**
     * Indicate that the friendship is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
        ]);
    }

    /**
     * Indicate that the friendship is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the friendship is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }
}
