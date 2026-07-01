<?php

declare(strict_types=1);

namespace Tests\Feature\UrlShortener;

use App\Filament\Admin\Resources\ShortLinkResource\Pages\CreateShortLink;
use App\Filament\Admin\Resources\ShortLinkResource\Pages\EditShortLink;
use App\Filament\Admin\Resources\ShortLinkResource\Pages\ListShortLinks;
use App\Contracts\UrlShortener\OriginalUrlReachabilityCheckerInterface;
use App\DTO\UrlShortener\OriginalUrlReachabilityResultDto;
use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Feature-тесты Filament CRUD коротких ссылок и изоляции данных по user_id. */
class ShortLinkFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_user_sees_only_own_short_links_in_table(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $ownLink = ShortLink::factory()->for($userA)->create(['code' => 'ownlink01']);
        $foreignLink = ShortLink::factory()->for($userB)->create(['code' => 'foreign01']);

        Livewire::actingAs($userA)
            ->test(ListShortLinks::class)
            ->assertCanSeeTableRecords([$ownLink])
            ->assertCanNotSeeTableRecords([$foreignLink]);
    }

    public function test_authenticated_user_can_create_short_link_via_filament(): void
    {
        $this->mockReachableOriginalUrl();

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateShortLink::class)
            ->set('data.original_url', 'https://example.com/new-link')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $shortLink = ShortLink::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($shortLink);
        $this->assertSame('https://example.com/new-link', $shortLink->original_url);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{4,12}$/', $shortLink->code);
        $this->assertSame(0, $shortLink->clicks_count);
    }

    public function test_create_rejects_invalid_original_url(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateShortLink::class)
            ->set('data.original_url', 'not-a-valid-url')
            ->call('create')
            ->assertHasFormErrors(['original_url']);

        $this->assertDatabaseMissing('short_links', ['user_id' => $user->id]);
    }

    public function test_create_rejects_empty_original_url(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateShortLink::class)
            ->set('data.original_url', '')
            ->call('create')
            ->assertHasFormErrors(['original_url' => 'required']);

        $this->assertDatabaseMissing('short_links', ['user_id' => $user->id]);
    }

    public function test_create_rejects_original_url_exceeding_max_length(): void
    {
        $user = User::factory()->create();
        $tooLongUrl = 'https://example.com/'.str_repeat('a', 2048);

        Livewire::actingAs($user)
            ->test(CreateShortLink::class)
            ->set('data.original_url', $tooLongUrl)
            ->call('create')
            ->assertHasFormErrors(['original_url']);

        $this->assertDatabaseMissing('short_links', ['user_id' => $user->id]);
    }

    public function test_create_prompts_confirmation_when_original_url_does_not_return_200(): void
    {
        $this->mockUnreachableOriginalUrl(statusCode: 404);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateShortLink::class)
            ->set('data.original_url', 'https://example.com/broken')
            ->call('create')
            ->assertActionMounted('confirmSaveBrokenUrl');

        $this->assertDatabaseMissing('short_links', ['user_id' => $user->id]);
    }

    public function test_create_can_save_broken_url_after_confirmation(): void
    {
        $this->mockUnreachableOriginalUrl(statusCode: 404);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateShortLink::class)
            ->set('data.original_url', 'https://example.com/broken')
            ->call('create')
            ->assertActionMounted('confirmSaveBrokenUrl')
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified();

        $shortLink = ShortLink::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($shortLink);
        $this->assertSame('https://example.com/broken', $shortLink->original_url);
    }

    public function test_edit_saves_updated_url_when_it_returns_200(): void
    {
        $this->mockReachableOriginalUrl();

        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'editok001',
            'original_url' => 'https://example.com/old',
        ]);

        Livewire::actingAs($user)
            ->test(EditShortLink::class, ['record' => $shortLink->getRouteKey()])
            ->set('data.original_url', 'https://example.com/new')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('https://example.com/new', $shortLink->fresh()->original_url);
    }

    public function test_edit_skips_url_check_when_original_url_unchanged(): void
    {
        $this->mock(OriginalUrlReachabilityCheckerInterface::class, function ($mock): void {
            $mock->shouldReceive('check')->never();
        });

        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'editskip1',
            'original_url' => 'https://example.com/unchanged',
        ]);

        Livewire::actingAs($user)
            ->test(EditShortLink::class, ['record' => $shortLink->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('https://example.com/unchanged', $shortLink->fresh()->original_url);
    }

    public function test_edit_prompts_confirmation_when_changed_url_does_not_return_200(): void
    {
        $this->mockUnreachableOriginalUrl(statusCode: 500);

        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'editbad01',
            'original_url' => 'https://example.com/old',
        ]);

        Livewire::actingAs($user)
            ->test(EditShortLink::class, ['record' => $shortLink->getRouteKey()])
            ->set('data.original_url', 'https://example.com/broken')
            ->call('save')
            ->assertActionMounted('confirmSaveBrokenUrl');

        $this->assertSame('https://example.com/old', $shortLink->fresh()->original_url);
    }

    public function test_edit_can_save_broken_url_after_confirmation(): void
    {
        $this->mockUnreachableOriginalUrl(statusCode: 503);

        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'editconf1',
            'original_url' => 'https://example.com/old',
        ]);

        Livewire::actingAs($user)
            ->test(EditShortLink::class, ['record' => $shortLink->getRouteKey()])
            ->set('data.original_url', 'https://example.com/broken')
            ->call('save')
            ->assertActionMounted('confirmSaveBrokenUrl')
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('https://example.com/broken', $shortLink->fresh()->original_url);
    }

    public function test_user_can_delete_own_short_link(): void
    {
        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'deleteme1',
            'original_url' => 'https://example.com/delete',
        ]);

        Livewire::actingAs($user)
            ->test(EditShortLink::class, ['record' => $shortLink->getRouteKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('short_links', ['id' => $shortLink->id]);
    }

    public function test_user_cannot_edit_another_users_short_link(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $foreignLink = ShortLink::factory()->for($userB)->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($userA)
            ->test(EditShortLink::class, ['record' => $foreignLink->getRouteKey()]);
    }

    public function test_user_can_open_click_journal_modal_for_own_short_link(): void
    {
        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'clicklog01',
            'clicks_count' => 2,
        ]);

        $olderClick = ShortLinkClick::factory()->for($shortLink)->create([
            'ip_address' => '192.168.1.10',
            'visited_at' => now()->subHour(),
        ]);
        $newerClick = ShortLinkClick::factory()->for($shortLink)->create([
            'ip_address' => '10.0.0.5',
            'visited_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(ListShortLinks::class)
            ->mountTableAction('viewClicks', $shortLink)
            ->assertSee('Журнал кликов')
            ->assertSee('Код: clicklog01 · Всего кликов: 2')
            ->assertSee('192.168.1.10')
            ->assertSee('10.0.0.5')
            ->assertSeeInOrder([
                '10.0.0.5',
                '192.168.1.10',
            ]);
    }

    public function test_click_journal_modal_shows_empty_state(): void
    {
        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'noclicks01',
            'clicks_count' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(ListShortLinks::class)
            ->mountTableAction('viewClicks', $shortLink)
            ->assertSee('Журнал кликов')
            ->assertSee('Кликов пока нет.');
    }

    public function test_click_journal_modal_paginates_clicks(): void
    {
        $user = User::factory()->create();
        $shortLink = ShortLink::factory()->for($user)->create([
            'code' => 'paginate01',
            'clicks_count' => 12,
        ]);

        for ($index = 0; $index < 12; $index++) {
            ShortLinkClick::factory()->for($shortLink)->create([
                'ip_address' => sprintf('10.0.0.%d', $index + 1),
                'visited_at' => now()->subMinutes($index),
            ]);
        }

        Livewire::actingAs($user)
            ->test(\App\Livewire\ShortLinkClicksTable::class, ['shortLinkId' => $shortLink->id])
            ->assertCanSeeTableRecords(
                ShortLinkClick::query()
                    ->where('short_link_id', $shortLink->id)
                    ->orderByDesc('visited_at')
                    ->limit(10)
                    ->get()
            )
            ->assertCanNotSeeTableRecords(
                ShortLinkClick::query()
                    ->where('short_link_id', $shortLink->id)
                    ->orderBy('visited_at')
                    ->limit(2)
                    ->get()
            )
            ->call('gotoPage', 2, 'page')
            ->assertCanSeeTableRecords(
                ShortLinkClick::query()
                    ->where('short_link_id', $shortLink->id)
                    ->orderBy('visited_at')
                    ->limit(2)
                    ->get()
            );
    }

    public function test_guest_cannot_access_filament_short_links_list(): void
    {
        $this->get('/admin/short-links')->assertRedirect('/login');
    }

    private function mockReachableOriginalUrl(): void
    {
        $this->mock(OriginalUrlReachabilityCheckerInterface::class, function ($mock): void {
            $mock->shouldReceive('check')
                ->andReturn(new OriginalUrlReachabilityResultDto(
                    isReachable: true,
                    httpStatusCode: 200,
                ));
        });
    }

    private function mockUnreachableOriginalUrl(?int $statusCode): void
    {
        $this->mock(OriginalUrlReachabilityCheckerInterface::class, function ($mock) use ($statusCode): void {
            $mock->shouldReceive('check')
                ->once()
                ->andReturn(new OriginalUrlReachabilityResultDto(
                    isReachable: false,
                    httpStatusCode: $statusCode,
                ));
        });
    }
}
