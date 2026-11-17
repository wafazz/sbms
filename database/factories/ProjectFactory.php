<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '+1 month');
        $end = (clone $start)->modify('+'.fake()->numberBetween(20, 120).' days');

        return [
            'client_id' => Client::factory(),
            'code' => 'PRJ-TEST-'.fake()->unique()->numerify('####'),
            'name' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'priority' => fake()->randomElement(Priority::cases()),
            'start_date' => $start,
            'end_date' => $end,
            'budget' => fake()->randomFloat(2, 5000, 250000),
            'progress' => 0,
            'progress_override' => false,
        ];
    }
}
