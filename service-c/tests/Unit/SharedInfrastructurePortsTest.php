<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxLoadTestDataRepositoryInterface;
use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ApplicationEnvironmentInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\FileStorageInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Contracts\Shared\LocalFileWriterInterface;
use App\DTO\Shared\HttpResponseDto;
use App\DTO\Shared\PaginatedResultDto;
use App\DTO\Shared\UploadedFileDto;
use App\Infrastructure\Laravel\LaravelApplicationConfig;
use App\Infrastructure\Laravel\LaravelApplicationEnvironment;
use App\Infrastructure\Laravel\LaravelCacheStore;
use App\Infrastructure\Laravel\LaravelClock;
use App\Infrastructure\Laravel\LaravelFileStorage;
use App\Infrastructure\Laravel\LaravelHttpClient;
use App\Infrastructure\Laravel\LaravelJobDispatcher;
use App\Infrastructure\Laravel\LaravelLocalFileWriter;
use App\Infrastructure\Laravel\LaravelMaxMiniAppTokenIssuer;
use App\Repositories\Max\EloquentMaxLoadTestDataRepository;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Фаза 1: порты Shared + Laravel-адаптеры в DI.
 */
class SharedInfrastructurePortsTest extends TestCase
{
    public function test_shared_ports_are_bound_to_laravel_adapters(): void
    {
        $this->assertInstanceOf(LaravelClock::class, $this->app->make(ClockInterface::class));
        $this->assertInstanceOf(LaravelJobDispatcher::class, $this->app->make(JobDispatcherInterface::class));
        $this->assertInstanceOf(LaravelFileStorage::class, $this->app->make(FileStorageInterface::class));
        $this->assertInstanceOf(LaravelApplicationConfig::class, $this->app->make(ApplicationConfigInterface::class));
        $this->assertInstanceOf(LaravelHttpClient::class, $this->app->make(HttpClientInterface::class));
        $this->assertInstanceOf(LaravelCacheStore::class, $this->app->make(CacheStoreInterface::class));
        $this->assertInstanceOf(LaravelApplicationEnvironment::class, $this->app->make(ApplicationEnvironmentInterface::class));
        $this->assertInstanceOf(LaravelLocalFileWriter::class, $this->app->make(LocalFileWriterInterface::class));
        $this->assertInstanceOf(LaravelMaxMiniAppTokenIssuer::class, $this->app->make(MaxMiniAppTokenIssuerInterface::class));
        $this->assertInstanceOf(EloquentMaxLoadTestDataRepository::class, $this->app->make(MaxLoadTestDataRepositoryInterface::class));
        $this->assertInstanceOf(LoggerInterface::class, $this->app->make(LoggerInterface::class));
    }

    public function test_clock_returns_datetime_immutable(): void
    {
        $now = $this->app->make(ClockInterface::class)->now();

        $this->assertInstanceOf(DateTimeImmutable::class, $now);
    }

    public function test_clock_respects_carbon_travel_to(): void
    {
        $frozen = Carbon::parse('2026-08-19 10:00:00');
        $this->travelTo($frozen);

        $now = $this->app->make(ClockInterface::class)->now();

        $this->assertSame($frozen->format(DateTimeImmutable::ATOM), $now->format(DateTimeImmutable::ATOM));
    }

    public function test_application_config_reads_values(): void
    {
        $config = $this->app->make(ApplicationConfigInterface::class);

        $this->assertIsString($config->get('app.name'));
    }

    public function test_cache_store_put_get_forget(): void
    {
        $cache = $this->app->make(CacheStoreInterface::class);
        $key = 'shared.ports.test.'.uniqid('', true);

        $this->assertTrue($cache->put($key, 'value', 60));
        $this->assertSame('value', $cache->get($key));
        $this->assertTrue($cache->forget($key));
        $this->assertNull($cache->get($key));
    }

    public function test_http_client_returns_response_dto(): void
    {
        Http::fake([
            'https://example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->app->make(HttpClientInterface::class)->request(
            method: 'GET',
            url: '/ping',
            baseUrl: 'https://example.test',
        );

        $this->assertInstanceOf(HttpResponseDto::class, $response);
        $this->assertTrue($response->successful);
        $this->assertSame(200, $response->status);
        $this->assertTrue($response->json('ok'));
    }

    public function test_paginated_result_dto(): void
    {
        $page = new PaginatedResultDto(
            items: ['a', 'b'],
            total: 5,
            perPage: 2,
            currentPage: 1,
            lastPage: 3,
        );

        $this->assertSame(1, $page->currentPage);
        $this->assertTrue($page->currentPage < $page->lastPage);
        $this->assertSame(['a', 'b'], $page->items);
    }

    public function test_uploaded_file_dto_client_extension(): void
    {
        $file = new UploadedFileDto(
            path: '/tmp/photo.JPG',
            originalName: 'photo.JPG',
            mimeType: 'image/jpeg',
            size: 1024,
        );

        $this->assertSame('jpg', $file->extension());
    }
}
