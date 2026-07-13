<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\TaskServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ListTasksRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API CRUD задач для Nuxt SPA (Sanctum: cookie + session).
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskServiceInterface $taskService,
    ) {}

    public function index(ListTasksRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->taskService->list($user, $request->toDto());

        return response()->json($result->toArray());
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $task = $this->taskService->create($user, $request->toDto());

        return response()->json([
            'data' => $task->toArray(),
        ], 201);
    }

    public function show(Request $request, int $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $taskDto = $this->taskService->show($user, $task);

        return response()->json([
            'data' => $taskDto->toArray(),
        ]);
    }

    public function update(UpdateTaskRequest $request, int $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $taskDto = $this->taskService->update($user, $task, $request->toDto());

        return response()->json([
            'data' => $taskDto->toArray(),
        ]);
    }

    public function destroy(Request $request, int $task): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->taskService->delete($user, $task);

        return response()->noContent();
    }
}
