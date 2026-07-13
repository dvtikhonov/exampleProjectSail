<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Заполняет БД тестовыми пользователями и задачами для локальной разработки.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
        ]);

        Task::factory()
            ->count(fake()->numberBetween(5, 10))
            ->for($admin)
            ->create();

        Task::factory()
            ->count(fake()->numberBetween(5, 10))
            ->for($user)
            ->create();
    }
}
