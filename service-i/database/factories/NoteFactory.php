<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * Возвращает набор атрибутов заметки по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->words(3, true);

        $title = fake()->randomElement([
            $words,
            mb_strtoupper($words),
            mb_strtolower($words),
            mb_convert_case($words, MB_CASE_TITLE, 'UTF-8'),
        ]);

        return [
            'title' => $title,
            'content' => fake()->optional(0.8)->paragraph(),
            'tags' => fake()->boolean(60)
                ? fake()->randomElements(['work', 'personal', 'ideas', 'todo', 'urgent'], fake()->numberBetween(1, 3))
                : null,
            'archived' => fake()->boolean(20),
            'meta' => fake()->boolean(30) ? ['source' => 'factory'] : null,
        ];
    }

    /** Состояние: без тегов. */
    public function withoutTags(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tags' => null,
        ]);
    }

    /** Состояние: архивная заметка. */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'archived' => true,
        ]);
    }
}
