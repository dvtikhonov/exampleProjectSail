<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-тесты NoteService (create / update / delete).
 */
class NoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private NoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NoteService::class);
    }

    /** create сохраняет заметку и возвращает модель. */
    public function test_create_persists_note(): void
    {
        $note = $this->service->create([
            'title' => 'Service note',
            'content' => 'From unit test',
            'tags' => ['unit'],
            'archived' => false,
        ]);

        $this->assertInstanceOf(Note::class, $note);
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Service note',
            'content' => 'From unit test',
            'archived' => false,
        ]);
        $this->assertSame(['unit'], $note->tags);
    }

    /** update изменяет переданные поля. */
    public function test_update_modifies_note(): void
    {
        $note = Note::factory()->create([
            'title' => 'Before',
            'content' => 'Keep me',
            'archived' => false,
        ]);

        $updated = $this->service->update($note->id, [
            'title' => 'After',
            'archived' => true,
        ]);

        $this->assertSame('After', $updated->title);
        $this->assertTrue($updated->archived);
        $this->assertSame('Keep me', $updated->content);
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'After',
            'archived' => true,
            'content' => 'Keep me',
        ]);
    }

    /** update для отсутствующей заметки бросает ModelNotFoundException. */
    public function test_update_throws_when_note_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->update(999999, ['title' => 'Nope']);
    }

    /** delete удаляет заметку. */
    public function test_delete_removes_note(): void
    {
        $note = Note::factory()->create(['title' => 'Disposable']);

        $this->service->delete($note->id);

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    /** delete для отсутствующей заметки бросает ModelNotFoundException. */
    public function test_delete_throws_when_note_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->delete(999999);
    }
}
