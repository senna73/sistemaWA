<?php

namespace Database\Factories;

use App\Models\Collaborator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collaborator>
 */
class CollaboratorFactory extends Factory
{
    protected $model = Collaborator::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'document' => fake()->unique()->numerify('###########'),
            'pix_key' => fake()->numerify('###########'),
            'mobile' => '119'.fake()->numerify('########'),
            'city' => fake()->city(),
            'active' => true,
            'is_leader' => false,
            'is_extra' => false,
        ];
    }
}
