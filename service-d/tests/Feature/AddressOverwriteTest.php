<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\YandexMaps\OrganizationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakesYandexParser;
use Tests\Support\MakesStatefulApiRequests;
use Tests\Support\YandexParserFixtures;
use Tests\TestCase;

class AddressOverwriteTest extends TestCase
{
    use FakesYandexParser;
    use MakesStatefulApiRequests;
    use RefreshDatabase;

    private const YANDEX_URL = 'https://yandex.ru/maps/org/test-cafe/1248139252/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpYandexParserConfig();
    }

    public function test_sync_does_not_overwrite_address_when_parser_returns_empty_address(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $resolveFixture = YandexParserFixtures::loadCollect('single_candidate');
        $syncFixture = YandexParserFixtures::load('sync_reviews');
        $syncFixture['org']['address'] = '';

        $this->fakeYandexParserCollect($resolveFixture);

        $resolveResponse = $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', ['url' => self::YANDEX_URL])
            ->assertOk();

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/confirm', [
                'session_id' => $resolveResponse->json('session_id'),
                'org_id' => '1248139252',
            ])
            ->assertAccepted();

        $organization = Organization::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Москва, ул. Тестовая, 1', $organization->address);

        Http::fake([
            '*/sync-reviews' => Http::response($syncFixture, 200),
        ]);

        app(OrganizationSyncService::class)->sync($organization->id);

        $this->assertSame(
            'Москва, ул. Тестовая, 1',
            $organization->fresh()->address,
            'Sync must preserve address when parser returns empty address.',
        );
    }
}
