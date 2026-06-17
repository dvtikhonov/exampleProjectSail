<?php

declare(strict_types=1);

namespace App\Clients;

use App\Contracts\YandexMapsClientInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\DTO\YandexMaps\ParsedReviewDto;
use App\Exceptions\YandexMaps\YandexMapsParserException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlaywrightYandexMapsClient implements YandexMapsClientInterface
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * @return array{resolved_url: string, candidates: OrganizationCandidateDto[]}
     */
    public function resolve(string $url): array
    {
        $response = $this->httpClient()->post('/resolve', [
            'url' => $url,
        ]);

        if (! $response->successful()) {
            $this->throwParserException($response->status(), $response->json('message', $response->body()));
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        $candidates = [];

        foreach ((array) ($body['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidates[] = OrganizationCandidateDto::fromParserArray($candidate);
        }

        return [
            'resolved_url' => (string) ($body['resolved_url'] ?? $url),
            'candidates' => $candidates,
        ];
    }

    /**
     * @return array{org: ParsedOrganizationMetaDto, reviews: ParsedReviewDto[]}
     */
    public function syncReviews(string $orgId, string $canonicalUrl): array
    {
        $response = $this->httpClient()->post('/sync-reviews', [
            'org_id' => $orgId,
            'canonical_url' => $canonicalUrl,
        ]);

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

    private function httpClient(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(300);
    }

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
