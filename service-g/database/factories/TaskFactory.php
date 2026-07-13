<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Возвращает набор атрибутов задачи по умолчанию для фабрики.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'due_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'status' => fake()->randomElement(TaskStatus::cases())->value,
        ];
    }

    /** Состояние фабрики: задача в статусе pending. */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /** Состояние фабрики: задача в статусе in_progress. */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::InProgress->value,
        ]);
    }

    /** Состояние фабрики: задача в статусе completed. */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed->value,
        ]);
    }
}
