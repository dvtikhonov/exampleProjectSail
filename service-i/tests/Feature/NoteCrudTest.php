<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-тесты публичного CRUD /api/notes.
 */
class NoteCrudTest extends TestCase
{
    use RefreshDatabase;

    /** POST /api/notes создаёт заметку и возвращает 201 с NoteResource. */
    public function test_store_creates_note(): void
    {
        $response = $this->postJson('/api/notes', [
            'title' => 'First note',
            'content' => 'Hello world',
            'tags' => ['work', 'ideas'],
            'archived' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'First note')
            ->assertJsonPath('data.content', 'Hello world')
            ->assertJsonPath('data.tags', ['work', 'ideas'])
            ->assertJsonPath('data.archived', false)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'content', 'tags', 'archived', 'created_at', 'updated_at'],
            ])
            ->assertJsonMissingPath('data.meta');

        $this->assertDatabaseHas('notes', [
            'title' => 'First note',
            'content' => 'Hello world',
            'archived' => false,
        ]);
    }

    /** POST /api/notes без title возвращает 422. */
    public function test_store_requires_title(): void
    {
        $this->postJson('/api/notes', [
            'content' => 'No title here',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    /** POST /api/notes со слишком длинным title возвращает 422. */
    public function test_store_validates_title_max_length(): void
    {
        $this->postJson('/api/notes', [
            'title' => str_repeat('a', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    /** GET /api/notes/{id} возвращает NoteResource. */
    public function test_show_returns_note(): void
    {
        $note = Note::factory()->create([
            'title' => 'Show me',
            'content' => 'Body',
            'tags' => ['personal'],
            'archived' => false,
            'meta' => ['source' => 'test'],
        ]);

        $this->getJson('/api/notes/'.$note->id)
            ->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.title', 'Show me')
            ->assertJsonPath('data.content', 'Body')
            ->assertJsonPath('data.tags', ['personal'])
            ->assertJsonPath('data.archived', false)
            ->assertJsonMissingPath('data.meta');
    }

    /** GET /api/notes/{id} для отсутствующей заметки возвращает 404. */
    public function test_show_returns_not_found_for_missing_note(): void
    {
        $this->getJson('/api/notes/999999')->assertNotFound();
    }

    /** PATCH /api/notes/{id} обновляет поля и возвращает NoteResource. */
    public function test_update_modifies_note(): void
    {
        $note = Note::factory()->create([
            'title' => 'Original',
            'content' => 'Old content',
            'archived' => false,
        ]);

        $this->patchJson('/api/notes/'.$note->id, [
            'title' => 'Updated',
            'archived' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.archived', true)
            ->assertJsonPath('data.content', 'Old content');

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated',
            'archived' => true,
            'content' => 'Old content',
        ]);
    }

    /** DELETE /api/notes/{id} удаляет заметку и возвращает 204. */
    public function test_destroy_deletes_note(): void
    {
        $note = Note::factory()->create(['title' => 'To delete']);

        $this->deleteJson('/api/notes/'.$note->id)->assertNoContent();

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    /** GET /api/notes (заглушка) возвращает сырой список моделей. */
    public function test_index_returns_all_notes_as_raw_json(): void
    {
        Note::factory()->create(['title' => 'Alpha']);
        Note::factory()->create(['title' => 'Beta']);

        $response = $this->getJson('/api/notes');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['title' => 'Alpha'])
            ->assertJsonFragment(['title' => 'Beta']);
    }

    /**
     * Заготовка: фильтры GET /api/notes ещё не реализованы.
     *
     * @see NoteService::list() TODO filters, sort, pagination
     */
    public function test_index_filters_are_not_implemented_yet(): void
    {
        $this->markTestIncomplete(
            'Filters, sort and pagination for GET /api/notes are not implemented yet.'
        );
    }
}
