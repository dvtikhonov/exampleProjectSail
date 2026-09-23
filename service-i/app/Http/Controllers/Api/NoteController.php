<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\IndexNoteRequest;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Публичный API CRUD заметок.
 */
class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
    ) {}

    /**
     * Список заметок: envelope { items, total, limit, offset }.
     *
     * NoteResource::collection(...)->resolve() обязателен, иначе Laravel
     * обернёт коллекцию в { data: [...] } и сломает контракт items.
     */
    public function index(IndexNoteRequest $request): JsonResponse
    {
        $result = $this->noteService->list($filters = $request->filters());

        return response()->json([
            'items' => NoteResource::collection($result['items'])->resolve(),
            'total' => $result['total'],
            'limit' => $filters->limit,
            'offset' => $filters->offset,
        ]);
    }

    /**
     * Создаёт заметку и возвращает NoteResource (201).
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $this->noteService->create($request->validated());

        return (new NoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Возвращает одну заметку через NoteResource.
     */
    public function show(int $note): NoteResource
    {
        return new NoteResource($this->noteService->find($note));
    }

    /**
     * Обновляет заметку и возвращает NoteResource.
     */
    public function update(UpdateNoteRequest $request, int $note): NoteResource
    {
        $updated = $this->noteService->update($note, $request->validated());

        return new NoteResource($updated);
    }

    /**
     * Удаляет заметку (204 No Content).
     */
    public function destroy(int $note): Response
    {
        $this->noteService->delete($note);

        return response()->noContent();
    }
}
