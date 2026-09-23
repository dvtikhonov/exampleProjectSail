<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dto\Note\NoteListFilters;
use App\Enums\NoteArchivedFilter;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-тесты NoteService (list / create / update / delete).
 *
 * Defaults фильтров задаются в IndexNoteRequest::filters(), не в list().
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

    /** list фильтрует по q+tags (OR в closure + AND tags) и режет limit/offset. */
    public function test_list_applies_filters_sort_and_pagination(): void
    {
        Note::factory()->create([
            'title' => 'laravel match',
            'content' => 'body',
            'tags' => ['work'],
            'archived' => false,
            'updated_at' => now()->subDay(),
        ]);
        Note::factory()->create([
            'title' => 'laravel no tag',
            'content' => 'body',
            'tags' => ['urgent'],
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'other work',
            'content' => 'no q',
            'tags' => ['work'],
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'laravel archived',
            'content' => 'body',
            'tags' => ['work'],
            'archived' => true,
        ]);

        $filters = new NoteListFilters(
            q: 'laravel',
            tags: ['work'],
            archived: NoteArchivedFilter::Active,
            sort: 'title',
            limit: 10,
            offset: 0,
        );

        $result = $this->service->list($filters);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('laravel match', $result['items']->first()->title);
    }

    /** list с offset уважает переданный limit/offset из DTO (без своих defaults). */
    public function test_list_uses_filters_limit_and_offset_as_provided(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E'] as $title) {
            Note::factory()->create([
                'title' => $title,
                'archived' => false,
            ]);
        }

        $filters = new NoteListFilters(
            q: null,
            tags: [],
            archived: NoteArchivedFilter::All,
            sort: 'title',
            limit: 2,
            offset: 2,
        );

        $result = $this->service->list($filters);

        $this->assertSame(5, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertSame(['C', 'D'], $result['items']->pluck('title')->all());
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
