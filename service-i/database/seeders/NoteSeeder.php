<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Note;
use Illuminate\Database\Seeder;

/**
 * Наполняет БД демонстрационными заметками (8–12 шт.).
 */
class NoteSeeder extends Seeder
{
    /**
     * Создаёт набор заметок с разным регистром title и частью без tags.
     */
    public function run(): void
    {
        $count = fake()->numberBetween(8, 12);

        Note::factory()
            ->count($count)
            ->sequence(
                ['title' => 'Meeting Notes', 'tags' => ['work', 'todo']],
                ['title' => 'shopping list', 'tags' => null],
                ['title' => 'IDEAS FOR WEEKEND', 'tags' => ['ideas', 'personal']],
                ['title' => 'Quick Reminder', 'tags' => ['urgent']],
                ['title' => 'draft article', 'tags' => null],
                ['title' => 'Project Roadmap', 'tags' => ['work']],
                ['title' => 'BOOKS TO READ', 'tags' => ['personal']],
                ['title' => 'empty scratch', 'tags' => null],
                ['title' => 'Team Sync', 'tags' => ['work', 'todo']],
                ['title' => 'random thought', 'tags' => ['ideas']],
                ['title' => 'ARCHIVE CANDIDATE', 'tags' => null, 'archived' => true],
                ['title' => 'Travel Checklist', 'tags' => ['personal', 'todo']],
            )
            ->create();
    }
}
