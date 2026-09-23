<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** GET /api/notes возвращает envelope { items, total, limit, offset } без meta. */
    public function test_index_returns_envelope_with_note_resource_items(): void
    {
        Note::factory()->create([
            'title' => 'Alpha',
            'tags' => null,
            'archived' => false,
            'meta' => ['source' => 'hidden'],
        ]);
        Note::factory()->create([
            'title' => 'Beta',
            'archived' => false,
        ]);

        $response = $this->getJson('/api/notes');

        $response->assertOk()
            ->assertJsonStructure([
                'items' => [
                    '*' => ['id', 'title', 'content', 'tags', 'archived', 'created_at', 'updated_at'],
                ],
                'total',
                'limit',
                'offset',
            ])
            ->assertJsonPath('total', 2)
            ->assertJsonPath('limit', 20)
            ->assertJsonPath('offset', 0)
            ->assertJsonCount(2, 'items')
            ->assertJsonFragment(['title' => 'Alpha'])
            ->assertJsonFragment(['title' => 'Beta'])
            ->assertJsonMissingPath('items.0.meta')
            ->assertJsonMissingPath('data');

        $alpha = collect($response->json('items'))->firstWhere('title', 'Alpha');
        $this->assertIsArray($alpha);
        $this->assertSame([], $alpha['tags']);
        $this->assertArrayNotHasKey('meta', $alpha);
    }

    /** Без query — defaults: archived=false, sort=-updated_at, limit=20, offset=0. */
    public function test_index_applies_defaults_and_excludes_archived(): void
    {
        Note::factory()->create(['title' => 'Active', 'archived' => false]);
        Note::factory()->create(['title' => 'Hidden', 'archived' => true]);

        $this->getJson('/api/notes')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('limit', 20)
            ->assertJsonPath('offset', 0)
            ->assertJsonPath('items.0.title', 'Active');
    }

    /** ?limit=50 приходит строкой из query — в envelope limit остаётся int 50. */
    public function test_index_casts_limit_query_string_to_int(): void
    {
        Note::factory()->count(3)->create(['archived' => false]);

        $response = $this->getJson('/api/notes?limit=50');

        $response->assertOk()
            ->assertJsonPath('limit', 50)
            ->assertJsonPath('total', 3);

        $this->assertIsInt($response->json('limit'));
        $this->assertIsInt($response->json('offset'));
    }

    /** q ищет по title OR content; content=null не ломает OR. */
    public function test_index_q_matches_title_or_content_including_null_content(): void
    {
        Note::factory()->create([
            'title' => 'Laravel tips',
            'content' => null,
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'Other',
            'content' => 'Learn laravel basics',
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'Unrelated',
            'content' => 'Nothing here',
            'archived' => false,
        ]);

        $response = $this->getJson('/api/notes?q=laravel');

        $response->assertOk()->assertJsonPath('total', 2);
        $titles = collect($response->json('items'))->pluck('title')->all();
        $this->assertContains('Laravel tips', $titles);
        $this->assertContains('Other', $titles);
        $this->assertNotContains('Unrelated', $titles);
    }

    /** ASCII case-insensitive: q=laravel находит "Laravel". */
    public function test_index_q_is_ascii_case_insensitive(): void
    {
        Note::factory()->create([
            'title' => 'Laravel',
            'content' => 'docs',
            'archived' => false,
        ]);

        $this->getJson('/api/notes?q=laravel')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.title', 'Laravel');
    }

    /**
     * OR по q сгруппирован: AND с tags не «протекает».
     * title=laravel + tags=[urgent] при q=laravel&tags=work → не в выдаче.
     */
    public function test_index_q_or_does_not_break_tags_and(): void
    {
        Note::factory()->create([
            'title' => 'laravel',
            'content' => 'mismatch tags',
            'tags' => ['urgent'],
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'laravel guide',
            'content' => 'ok',
            'tags' => ['work'],
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'No match',
            'content' => 'work only',
            'tags' => ['work'],
            'archived' => false,
        ]);

        $response = $this->getJson('/api/notes?q=laravel&tags=work');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.title', 'laravel guide');
    }

    /** Несколько tags — AND; порядок в query не важен. */
    public function test_index_tags_csv_applies_and_filter(): void
    {
        Note::factory()->create([
            'title' => 'Both',
            'tags' => ['work', 'urgent'],
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'Only work',
            'tags' => ['work'],
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'Reversed order in DB',
            'tags' => ['urgent', 'work'],
            'archived' => false,
        ]);

        $response = $this->getJson('/api/notes?tags=urgent,work');

        $response->assertOk()->assertJsonPath('total', 2);
        $titles = collect($response->json('items'))->pluck('title')->all();
        $this->assertContains('Both', $titles);
        $this->assertContains('Reversed order in DB', $titles);
        $this->assertNotContains('Only work', $titles);
    }

    /** ?tags[]=work&tags[]=urgent эквивалентен CSV tags=work,urgent. */
    public function test_index_tags_array_format_equals_csv(): void
    {
        Note::factory()->create([
            'title' => 'Match',
            'tags' => ['work', 'urgent'],
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'Partial',
            'tags' => ['work'],
            'archived' => false,
        ]);

        $csv = $this->getJson('/api/notes?tags=work,urgent');
        $array = $this->getJson('/api/notes?tags[]=work&tags[]=urgent');

        $csv->assertOk()->assertJsonPath('total', 1);
        $array->assertOk()->assertJsonPath('total', 1);
        $this->assertSame($csv->json('items.0.id'), $array->json('items.0.id'));
    }

    /** Пустой tags / tags= → фильтр не применяется, 200. */
    public function test_index_empty_tags_returns_ok_without_filter(): void
    {
        Note::factory()->create([
            'title' => 'No tags',
            'tags' => null,
            'archived' => false,
        ]);
        Note::factory()->create([
            'title' => 'With tag',
            'tags' => ['work'],
            'archived' => false,
        ]);

        $this->getJson('/api/notes?tags=')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/notes?tags[]=')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    /** sort=title и -title меняют порядок. */
    public function test_index_sort_by_title(): void
    {
        Note::factory()->create(['title' => 'Charlie', 'archived' => false]);
        Note::factory()->create(['title' => 'Alpha', 'archived' => false]);
        Note::factory()->create(['title' => 'Bravo', 'archived' => false]);

        $asc = $this->getJson('/api/notes?sort=title');
        $asc->assertOk();
        $this->assertSame(
            ['Alpha', 'Bravo', 'Charlie'],
            collect($asc->json('items'))->pluck('title')->all()
        );

        $desc = $this->getJson('/api/notes?sort=-title');
        $desc->assertOk();
        $this->assertSame(
            ['Charlie', 'Bravo', 'Alpha'],
            collect($desc->json('items'))->pluck('title')->all()
        );
    }

    /** sort=created_at и -created_at меняют порядок по дате создания. */
    public function test_index_sort_by_created_at(): void
    {
        Note::factory()->create([
            'title' => 'Oldest',
            'archived' => false,
            'created_at' => now()->subDays(3),
        ]);
        Note::factory()->create([
            'title' => 'Middle',
            'archived' => false,
            'created_at' => now()->subDays(2),
        ]);
        Note::factory()->create([
            'title' => 'Newest',
            'archived' => false,
            'created_at' => now()->subDay(),
        ]);

        $asc = $this->getJson('/api/notes?sort=created_at');
        $asc->assertOk();
        $this->assertSame(
            ['Oldest', 'Middle', 'Newest'],
            collect($asc->json('items'))->pluck('title')->all()
        );

        $desc = $this->getJson('/api/notes?sort=-created_at');
        $desc->assertOk();
        $this->assertSame(
            ['Newest', 'Middle', 'Oldest'],
            collect($desc->json('items'))->pluck('title')->all()
        );
    }

    /** 30 заметок, limit=10, offset=10 → total=30, len(items)=10. */
    public function test_index_pagination_limit_and_offset(): void
    {
        Note::factory()->count(30)->create(['archived' => false]);

        $response = $this->getJson('/api/notes?limit=10&offset=10');

        $response->assertOk()
            ->assertJsonPath('total', 30)
            ->assertJsonPath('limit', 10)
            ->assertJsonPath('offset', 10)
            ->assertJsonCount(10, 'items');
    }

    /** archived=all включает архивные. */
    public function test_index_archived_all_includes_archived_notes(): void
    {
        Note::factory()->create(['title' => 'Active', 'archived' => false]);
        Note::factory()->create(['title' => 'Archived', 'archived' => true]);

        $this->getJson('/api/notes?archived=all')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    #[DataProvider('indexValidationErrorProvider')]
    public function test_index_returns_422_for_invalid_query(string $query, string $errorKey): void
    {
        $this->getJson('/api/notes?'.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function indexValidationErrorProvider(): array
    {
        return [
            'sort whitelist' => ['sort=foo', 'sort'],
            'limit zero' => ['limit=0', 'limit'],
            'limit over max' => ['limit=101', 'limit'],
            'limit non-integer' => ['limit=abc', 'limit'],
            'offset negative' => ['offset=-1', 'offset'],
            'offset non-integer' => ['offset=x', 'offset'],
            'archived invalid' => ['archived=maybe', 'archived'],
            'q too long' => ['q='.str_repeat('a', 256), 'q'],
            'tag too long' => ['tags='.str_repeat('t', 51), 'tags.0'],
        ];
    }
}
