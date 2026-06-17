<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Clients\PlaywrightYandexMapsClient;
use App\Contracts\YandexMapsClientInterface;
use Illuminate\Support\Facades\Http;

/**
 * Http::fake для PlaywrightYandexMapsClient в feature-тестах.
 */
trait FakesYandexParser
{
    protected string $yandexParserBaseUrl = 'http://yandex-parser.test';

    protected function setUpYandexParserConfig(): void
    {
        config()->set('services.yandex_parser.url', $this->yandexParserBaseUrl);

        $this->app->bind(YandexMapsClientInterface::class, fn (): PlaywrightYandexMapsClient => new PlaywrightYandexMapsClient(
            baseUrl: $this->yandexParserBaseUrl,
        ));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function fakeYandexParserResolve(array $body, int $status = 200): void
    {
        Http::fake([
            $this->yandexParserBaseUrl.'/resolve' => Http::response($body, $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function fakeYandexParserSyncReviews(array $body, int $status = 200): void
    {
        Http::fake([
            $this->yandexParserBaseUrl.'/sync-reviews' => Http::response($body, $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $resolveBody
     * @param  array<string, mixed>  $syncBody
     */
    protected function fakeYandexParser(array $resolveBody, array $syncBody): void
    {
        Http::fake([
            $this->yandexParserBaseUrl.'/resolve' => Http::response($resolveBody, 200),
            $this->yandexParserBaseUrl.'/sync-reviews' => Http::response($syncBody, 200),
        ]);
    }
}
