<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Публичный API CRUD заметок.
 *
 * Index — намеренная заглушка без Resource/фильтров/пагинации.
 */
class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
    ) {}

    /**
     * Заглушка списка: отдаёт все заметки как сырой JSON-массив моделей.
     */
    public function index(): JsonResponse
    {
        return response()->json(Note::all());
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
