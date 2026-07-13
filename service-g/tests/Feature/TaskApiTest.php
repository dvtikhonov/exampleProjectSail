<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesStatefulApiRequests;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use MakesStatefulApiRequests;
    use RefreshDatabase;

    /** Гость не может получить список задач. */
    public function test_guest_cannot_access_tasks_index(): void
    {
        $this->getStatefulJson('/api/tasks')
            ->assertUnauthorized();
    }

    /** Гость не может создать задачу. */
    public function test_guest_cannot_create_task(): void
    {
        $this->postStatefulJson('/api/tasks', $this->validTaskPayload())
            ->assertUnauthorized();
    }

    /** Пользователь создаёт задачу и получает 201 с user_id текущего пользователя. */
    public function test_user_can_create_task(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsStateful($user)->postStatefulJson('/api/tasks', $this->validTaskPayload([
            'title' => 'New task title',
            'description' => 'Task description',
            'status' => TaskStatus::Pending->value,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.title', 'New task title')
            ->assertJsonPath('data.description', 'Task description')
            ->assertJsonPath('data.status', TaskStatus::Pending->value)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'description', 'due_date', 'status', 'created_at', 'updated_at', 'user_id'],
            ]);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'New task title',
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /** Слишком короткий title при создании возвращает 422. */
    public function test_create_task_validates_title_min_length(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)->postStatefulJson('/api/tasks', $this->validTaskPayload([
            'title' => 'ab',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    /** Отсутствующий due_date при создании возвращает 422. */
    public function test_create_task_requires_due_date(): void
    {
        $user = User::factory()->create();

        $payload = $this->validTaskPayload();
        unset($payload['due_date']);

        $this->actingAsStateful($user)->postStatefulJson('/api/tasks', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    }

    /** Пустой due_date при создании возвращает 422. */
    public function test_create_task_rejects_empty_due_date(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)->postStatefulJson('/api/tasks', $this->validTaskPayload([
            'due_date' => null,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    }

    /** Пользователь видит в списке только свои задачи. */
    public function test_user_sees_only_own_tasks_in_list(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownTask = Task::factory()->for($user)->create(['title' => 'My task']);
        Task::factory()->for($otherUser)->create(['title' => 'Foreign task']);

        $response = $this->actingAsStateful($user)->getStatefulJson('/api/tasks');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownTask->id)
            ->assertJsonPath('data.0.title', 'My task')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /** Пользователь не может просмотреть чужую задачу. */
    public function test_user_cannot_view_other_users_task(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignTask = Task::factory()->for($otherUser)->create();

        $this->actingAsStateful($user)->getStatefulJson('/api/tasks/'.$foreignTask->id)
            ->assertForbidden();
    }

    /** Пользователь не может обновить чужую задачу. */
    public function test_user_cannot_update_other_users_task(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignTask = Task::factory()->for($otherUser)->create();

        $this->actingAsStateful($user)->patchStatefulJson('/api/tasks/'.$foreignTask->id, [
            'title' => 'Hacked title',
        ])->assertForbidden();
    }

    /** Пользователь не может удалить чужую задачу. */
    public function test_user_cannot_delete_other_users_task(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignTask = Task::factory()->for($otherUser)->create();

        $this->actingAsStateful($user)->deleteStatefulJson('/api/tasks/'.$foreignTask->id)
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $foreignTask->id]);
    }

    /** Пользователь может обновить и удалить свою задачу. */
    public function test_user_can_update_and_delete_own_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->pending()->create(['title' => 'Original title']);

        $this->actingAsStateful($user)->patchStatefulJson('/api/tasks/'.$task->id, [
            'title' => 'Updated title',
            'status' => TaskStatus::Completed->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.status', TaskStatus::Completed->value);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated title',
            'status' => TaskStatus::Completed->value,
        ]);

        $this->actingAsStateful($user)->deleteStatefulJson('/api/tasks/'.$task->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /** Администратор видит задачи всех пользователей. */
    public function test_admin_sees_all_tasks_in_list(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $adminTask = Task::factory()->for($admin)->create(['title' => 'Admin task']);
        $userTask = Task::factory()->for($user)->create(['title' => 'User task']);

        $response = $this->actingAsStateful($admin)->getStatefulJson('/api/tasks');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($adminTask->id, $ids);
        $this->assertContains($userTask->id, $ids);
    }

    /** Администратор не может обновить или удалить чужую задачу. */
    public function test_admin_cannot_update_or_delete_other_users_task(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'User owned task']);

        $this->actingAsStateful($admin)->patchStatefulJson('/api/tasks/'.$task->id, [
            'title' => 'Admin updated',
        ])
            ->assertForbidden();

        $this->actingAsStateful($admin)->deleteStatefulJson('/api/tasks/'.$task->id)
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'User owned task',
        ]);
    }

    /** Администратор получает owner_name в ответе списка. */
    public function test_admin_list_includes_owner_name(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Task Owner']);
        Task::factory()->for($owner)->create();

        $response = $this->actingAsStateful($admin)->getStatefulJson('/api/tasks');

        $response->assertOk()
            ->assertJsonPath('data.0.owner_name', 'Task Owner');
    }

    /** Несуществующая задача возвращает 404. */
    public function test_show_returns_not_found_for_missing_task(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)->getStatefulJson('/api/tasks/999999')
            ->assertNotFound();
    }

    /** Фильтр status возвращает только задачи с указанным статусом. */
    public function test_user_can_filter_tasks_by_status(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->pending()->create(['title' => 'Pending task']);
        Task::factory()->for($user)->completed()->create(['title' => 'Completed task']);

        $response = $this->actingAsStateful($user)->getStatefulJson('/api/tasks?status='.TaskStatus::Pending->value);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', TaskStatus::Pending->value)
            ->assertJsonPath('data.0.title', 'Pending task');
    }

    /** Поиск search находит задачи по title и description. */
    public function test_user_can_search_tasks_by_title_and_description(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->create([
            'title' => 'Buy groceries',
            'description' => 'Regular shopping',
        ]);
        $matched = Task::factory()->for($user)->create([
            'title' => 'Other',
            'description' => 'UniqueSearchTokenXYZ in description',
        ]);
        Task::factory()->for($user)->create([
            'title' => 'Unrelated',
            'description' => 'Nothing here',
        ]);

        $byDescription = $this->actingAsStateful($user)->getStatefulJson('/api/tasks?search=UniqueSearchTokenXYZ');
        $byDescription->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->id);

        Task::factory()->for($user)->create(['title' => 'FindMeUniqueTitleABC']);

        $byTitle = $this->actingAsStateful($user)->getStatefulJson('/api/tasks?search=FindMeUniqueTitle');
        $byTitle->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'FindMeUniqueTitleABC');
    }

    /** Сортировка sort=due_date упорядочивает задачи по сроку. */
    public function test_user_can_sort_tasks_by_due_date(): void
    {
        $user = User::factory()->create();

        $later = Task::factory()->for($user)->create([
            'title' => 'Later task',
            'due_date' => now()->addDays(10)->format('Y-m-d'),
        ]);
        $earlier = Task::factory()->for($user)->create([
            'title' => 'Earlier task',
            'due_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response = $this->actingAsStateful($user)->getStatefulJson('/api/tasks?sort=due_date&direction=asc');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $earlier->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    /** Пагинация page=2 возвращает вторую страницу результатов. */
    public function test_user_can_paginate_tasks(): void
    {
        $user = User::factory()->create();

        Task::factory()->for($user)->count(5)->create();

        $response = $this->actingAsStateful($user)->getStatefulJson('/api/tasks?per_page=2&page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    /**
     * Валидный payload для POST /api/tasks.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTaskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Valid task title',
            'description' => null,
            'due_date' => now()->addWeek()->format('Y-m-d'),
            'status' => TaskStatus::Pending->value,
        ], $overrides);
    }
}
