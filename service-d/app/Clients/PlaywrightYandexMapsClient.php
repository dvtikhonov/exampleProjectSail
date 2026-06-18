<?php

declare(strict_types=1);

namespace App\Clients;

use App\Contracts\YandexMapsClientInterface;
use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\DTO\YandexMaps\ParsedReviewDto;
use App\DTO\YandexMaps\ParserCollectResultDto;
use App\Exceptions\YandexMaps\YandexMapsParserException;
use App\Services\YandexMaps\OrganizationResolveService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP-клиент к микросервису yandex-parser (Playwright).
 *
 * Инкапсулирует вызовы REST API парсера и маппинг JSON-ответов в DTO.
 * Реализация контракта {@see YandexMapsClientInterface}; URL берётся из `config('services.yandex_parser.url')`.
 */
class PlaywrightYandexMapsClient implements YandexMapsClientInterface
{
    /**
     * @param  string  $baseUrl  Базовый URL yandex-parser (без завершающего слэша), например `http://yandex-parser:3000`
     */
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * Запрашивает сырой collect страницы Яндекс.Карт по URL.
     *
     * Вызывает `POST /resolve` — навигация, перехват JSON и dom_harvest без готовых кандидатов.
     * Дальнейшая сборка кандидатов выполняется в {@see OrganizationResolveService}.
     *
     * @throws YandexMapsParserException при HTTP-ошибке или недоступности парсера
     */
    public function collect(string $url): ParserCollectResultDto
    {
        $response = $this->httpClient()->post('/resolve', [
            'url' => $url,
        ]);

        if (! $response->successful()) {
            $this->throwParserException($response->status(), $response->json('message', $response->body()));
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return ParserCollectResultDto::fromParserArray($body);
    }

    /**
     * Синхронизирует метаданные организации и отзывы со страницы Яндекс.Карт.
     *
     * Вызывает `POST /sync-reviews`. Параметр `stopAnchors` — якоря уже известных отзывов
     * для инкрементальной подгрузки (парсер останавливается при встрече с ними).
     *
     * @param  string  $orgId  Идентификатор организации в Яндекс.Картах
     * @param  string  $canonicalUrl  Канонический URL страницы организации
     * @param  string[]  $stopAnchors  Якоря отзывов, при достижении которых парсер прекращает скролл
     * @return array{org: ParsedOrganizationMetaDto, reviews: ParsedReviewDto[]}
     *
     * @throws YandexMapsParserException при HTTP-ошибке или недоступности парсера
     */
    public function syncReviews(string $orgId, string $canonicalUrl, array $stopAnchors = []): array
    {
        $requestBody = [
            'org_id' => $orgId,
            'canonical_url' => $canonicalUrl,
        ];

        if ($stopAnchors !== []) {
            $requestBody['stop_anchors'] = $stopAnchors;
        }

        $response = $this->httpClient()->post('/sync-reviews', $requestBody);

        if (! $response->successful()) {
            $this->throwParserException($response->status(), $response->json('message', $response->body()));
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        $reviews = [];

        foreach ((array) ($body['reviews'] ?? []) as $review) {
            if (! is_array($review)) {
                continue;
            }

            $reviews[] = ParsedReviewDto::fromParserArray($review);
        }

        /** @var array<string, mixed> $orgData */
        $orgData = (array) ($body['org'] ?? []);

        return [
            'org' => ParsedOrganizationMetaDto::fromParserArray($orgData),
            'reviews' => $reviews,
        ];
    }

    /**
     * Собирает HTTP-клиент с базовым URL парсера.
     *
     * Таймаут 300 с — парсинг через Playwright может занимать несколько минут.
     */
    private function httpClient(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(300);
    }

    /**
     * Логирует сбой запроса и выбрасывает доменное исключение с безопасным сообщением для API.
     */
    private function throwParserException(int $status, mixed $message): void
    {
        $safeMessage = is_string($message) && $message !== ''
            ? $message
            : 'Не удалось обработать запрос к парсеру Яндекс.Карт.';

        Log::warning('Yandex parser request failed.', [
            'http_status' => $status,
            'message' => $safeMessage,
        ]);

        throw new YandexMapsParserException($safeMessage);
    }
}
