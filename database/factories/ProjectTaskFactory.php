<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+'.fake()->numberBetween(1, 14).' days'),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'progress' => fake()->numberBetween(0, 100),
            'assigned_to' => null,
            'sort_order' => 0,
        ];
    }
}
