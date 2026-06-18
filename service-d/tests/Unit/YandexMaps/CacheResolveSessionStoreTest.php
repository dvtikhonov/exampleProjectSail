<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\Exceptions\Organization\OrganizationResolveSessionExpiredException;
use App\Services\YandexMaps\CacheResolveSessionStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheResolveSessionStoreTest extends TestCase
{
    private CacheResolveSessionStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->store = new CacheResolveSessionStore;
    }

    public function test_put_and_get_round_trip(): void
    {
        $candidates = [
            new OrganizationCandidateDto(
                orgId: '1248139252',
                name: 'Test Cafe',
                address: 'Москва, ул. Тестовая, 1',
                averageRating: 4.5,
                reviewsCount: 10,
                ratingsCount: 12,
                canonicalUrl: 'https://yandex.ru/maps/org/test-cafe/1248139252/',
            ),
        ];

        $this->store->put(
            sessionId: 'session-abc',
            inputUrl: 'www.example.ru Москва',
            searchText: 'www.example.ru Москва',
            resolvedUrl: 'https://yandex.ru/maps/?text=example',
            candidates: $candidates,
            ttlSeconds: 900,
        );

        $session = $this->store->get('session-abc');

        $this->assertSame('www.example.ru Москва', $session->inputUrl);
        $this->assertSame('https://yandex.ru/maps/?text=example', $session->resolvedUrl);
        $this->assertCount(1, $session->candidates);
        $this->assertSame('1248139252', $session->candidates[0]->orgId);
        $this->assertSame('Test Cafe', $session->candidates[0]->name);
    }

    public function test_get_throws_when_session_expired(): void
    {
        $this->expectException(OrganizationResolveSessionExpiredException::class);

        $this->store->get('missing-session');
    }
}
