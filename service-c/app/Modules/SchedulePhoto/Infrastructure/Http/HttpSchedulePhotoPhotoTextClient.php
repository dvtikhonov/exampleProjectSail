<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Http;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogCategoryDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteRestaurantDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleEntryDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleIssueDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleMatchedDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoMatchIssueCode;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Support\SchedulePhotoOriginAllowList;
use Throwable;

/**
 * HTTP-адаптер PhotoText: restaurants, catalog, schedule/match и schedule/apply.
 *
 * Origin только из allow-list. Write token уходит только на apply.
 * Редиректы не следуются: 301/302 — ошибка origin, Location не читается.
 */
final class HttpSchedulePhotoPhotoTextClient implements SchedulePhotoPhotoTextClientInterface
{
    private const string AGENT_TOKEN_HEADER = 'X-PhotoText-Token';

    private const string WRITE_TOKEN_HEADER = 'X-PhotoText-Write-Token';

    private const string RESTAURANTS_PATH = '/api/food/phototext/restaurants';

    private const string CATALOG_PATH = '/api/food/phototext/catalog';

    private const string MATCH_PATH = '/api/food/phototext/schedule/match';

    private const string APPLY_PATH = '/api/food/phototext/schedule/apply';

    /** @var list<int> */
    private const array ORIGIN_REDIRECT_STATUSES = [301, 302];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly SchedulePhotoOriginAllowList $allowList,
        private readonly string $agentToken,
        private readonly string $writeToken,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listRestaurants(string $baseUrl): array
    {
        $payload = $this->requestJson('GET', self::RESTAURANTS_PATH, $this->origin($baseUrl), false, null);
        $rows = $payload['restaurants'] ?? null;
        if (! is_array($rows)) {
            throw new SchedulePhotoDomainException('PhotoText: пустой или неверный JSON.', 502);
        }

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id < 1 || $name === '') {
                continue;
            }

            $items[] = new SchedulePhotoRemoteRestaurantDto($id, $name);
        }

        return $items;
    }

    /**
     * {@inheritDoc}
     */
    public function getCatalog(string $baseUrl, int $restaurantId): SchedulePhotoRemoteCatalogDto
    {
        $this->assertRestaurantId($restaurantId);

        $query = http_build_query(['restaurant_id' => $restaurantId]);
        $payload = $this->requestJson(
            'GET',
            self::CATALOG_PATH.'?'.$query,
            $this->origin($baseUrl),
            false,
            null,
        );

        return $this->mapCatalog($payload);
    }

    /**
     * {@inheritDoc}
     */
    public function match(
        string $baseUrl,
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): SchedulePhotoScheduleResultDto {
        return $this->syncSchedule(
            self::MATCH_PATH,
            false,
            $baseUrl,
            $restaurantId,
            $categoryIds,
            $dateFrom,
            $dateTo,
            $entries,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function apply(
        string $baseUrl,
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): SchedulePhotoScheduleResultDto {
        return $this->syncSchedule(
            self::APPLY_PATH,
            true,
            $baseUrl,
            $restaurantId,
            $categoryIds,
            $dateFrom,
            $dateTo,
            $entries,
        );
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @param  list<SchedulePhotoScheduleEntryDto>  $entries
     */
    private function syncSchedule(
        string $path,
        bool $withWriteToken,
        string $baseUrl,
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): SchedulePhotoScheduleResultDto {
        $this->assertRestaurantId($restaurantId);

        $payload = $this->requestJson(
            'POST',
            $path,
            $this->origin($baseUrl),
            $withWriteToken,
            $this->scheduleBody($restaurantId, $categoryIds, $dateFrom, $dateTo, $entries),
        );

        return $this->mapScheduleResult($payload);
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @param  list<SchedulePhotoScheduleEntryDto>  $entries
     * @return array<string, mixed>
     */
    private function scheduleBody(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): array {
        $body = [
            'restaurant_id' => $restaurantId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'entries' => array_map(
                static fn (SchedulePhotoScheduleEntryDto $entry): array => [
                    'name' => $entry->name,
                    'dates' => array_values($entry->dates),
                ],
                $entries,
            ),
        ];

        if ($categoryIds !== null && $categoryIds !== []) {
            $body['category_ids'] = array_values(array_map(
                static fn (int $id): int => $id,
                $categoryIds,
            ));
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapCatalog(array $payload): SchedulePhotoRemoteCatalogDto
    {
        $catalog = $payload['catalog'] ?? null;
        if (! is_array($catalog)) {
            throw new SchedulePhotoDomainException('PhotoText: пустой или неверный JSON каталога.', 502);
        }

        $restaurantId = (int) ($catalog['restaurant_id'] ?? 0);
        if ($restaurantId < 1) {
            throw new SchedulePhotoDomainException('PhotoText: пустой или неверный JSON каталога.', 502);
        }

        $rows = $catalog['categories'] ?? null;
        if (! is_array($rows)) {
            throw new SchedulePhotoDomainException('PhotoText: пустой или неверный JSON каталога.', 502);
        }

        $categories = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id < 1 || $name === '') {
                continue;
            }

            $categories[] = new SchedulePhotoRemoteCatalogCategoryDto(
                $id,
                $name,
                $this->mapDishes($row['dishes'] ?? []),
            );
        }

        return new SchedulePhotoRemoteCatalogDto(
            restaurantId: $restaurantId,
            restaurantName: trim((string) ($catalog['restaurant_name'] ?? '')),
            categories: $categories,
        );
    }

    /**
     * @return list<SchedulePhotoRemoteCatalogDishDto>
     */
    private function mapDishes(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $dishes = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id < 1 || $name === '') {
                continue;
            }

            $dishes[] = new SchedulePhotoRemoteCatalogDishDto($id, $name);
        }

        return $dishes;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapScheduleResult(array $payload): SchedulePhotoScheduleResultDto
    {
        $matchedRows = $payload['matched'] ?? null;
        $dateFrom = $payload['date_from'] ?? null;
        $dateTo = $payload['date_to'] ?? null;

        if (! is_array($matchedRows) || ! is_string($dateFrom) || ! is_string($dateTo)) {
            throw new SchedulePhotoDomainException('PhotoText: пустой или неверный JSON.', 502);
        }

        $matched = [];
        foreach ($matchedRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $matched[] = new SchedulePhotoScheduleMatchedDto(
                rawTitle: (string) ($row['raw_title'] ?? ''),
                dishId: (int) ($row['dish_id'] ?? 0),
                dishName: (string) ($row['dish_name'] ?? ''),
                categoryId: (int) ($row['category_id'] ?? 0),
                categoryName: (string) ($row['category_name'] ?? ''),
                dates: $this->stringList($row['dates'] ?? []),
            );
        }

        $issues = [];
        $issueRows = $payload['issues'] ?? [];
        if (is_array($issueRows)) {
            foreach ($issueRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $code = SchedulePhotoMatchIssueCode::tryFrom((string) ($row['code'] ?? ''));
                if ($code === null) {
                    continue;
                }

                $issues[] = new SchedulePhotoScheduleIssueDto(
                    code: $code,
                    message: (string) ($row['message'] ?? ''),
                    rawTitle: (string) ($row['raw_title'] ?? ''),
                    dates: $this->stringList($row['dates'] ?? []),
                );
            }
        }

        $matchedCount = is_numeric($payload['matched_count'] ?? null)
            ? (int) $payload['matched_count']
            : count($matched);

        return new SchedulePhotoScheduleResultDto(
            matchedCount: $matchedCount,
            matched: $matched,
            issues: $issues,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            applied: ($payload['applied'] ?? false) === true,
            categoriesApplied: $this->intList($payload['categories_applied'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $list[] = $value;
            }
        }

        return $list;
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $id = (int) $value;
                if ($id > 0) {
                    $list[] = $id;
                }
            }
        }

        return $list;
    }

    private function assertRestaurantId(int $restaurantId): void
    {
        if ($restaurantId < 1) {
            throw new SchedulePhotoDomainException('Укажите restaurant_id.', 422);
        }
    }

    private function origin(string $baseUrl): string
    {
        if (trim($baseUrl) === '') {
            throw new SchedulePhotoDomainException('BASE_URL пуст.', 422);
        }

        $origin = $this->allowList->normalize($baseUrl);
        if ($origin === null) {
            throw new SchedulePhotoDomainException(SchedulePhotoOriginAllowList::REJECTION_MESSAGE, 422);
        }

        return $origin;
    }

    /**
     * Запрос к allow-listed origin без следования редиректам.
     * 301/302 — ошибка origin: тело и Location не читаются, токены повторно не уходят.
     *
     * @param  array<string, mixed>|null  $jsonBody
     * @return array<string, mixed>
     */
    private function requestJson(
        string $method,
        string $path,
        string $origin,
        bool $withWriteToken,
        ?array $jsonBody,
    ): array {
        try {
            $response = $this->http->request(
                $method,
                $path,
                $this->headers($withWriteToken),
                $jsonBody,
                $origin,
                $this->timeoutSeconds,
                followRedirects: false,
            );
        } catch (Throwable $exception) {
            throw new SchedulePhotoDomainException(
                'PhotoText недоступен: '.$exception->getMessage(),
                503,
            );
        }

        if (in_array($response->status, self::ORIGIN_REDIRECT_STATUSES, true)) {
            throw new SchedulePhotoDomainException(
                'PhotoText: редирект ('.$response->status.') отклонён, origin не подтверждён.',
                502,
            );
        }

        if (! $response->successful) {
            throw $this->mapHttpFailure($response, $method, $path);
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new SchedulePhotoDomainException('PhotoText: пустой или неверный JSON.', 502);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private function headers(bool $withWriteToken): array
    {
        $agentToken = trim($this->agentToken);
        if ($agentToken === '') {
            throw new SchedulePhotoDomainException(
                'PhotoText agent token не настроен на сервере.',
                503,
            );
        }

        $headers = [
            'Accept' => 'application/json',
            self::AGENT_TOKEN_HEADER => $agentToken,
        ];

        if (! $withWriteToken) {
            return $headers;
        }

        $writeToken = trim($this->writeToken);
        if ($writeToken === '') {
            throw new SchedulePhotoDomainException(
                'PhotoText write token не настроен на сервере.',
                503,
            );
        }

        $headers[self::WRITE_TOKEN_HEADER] = $writeToken;

        return $headers;
    }

    private function mapHttpFailure(HttpResponseDto $response, string $method, string $path): SchedulePhotoDomainException
    {
        $pathOnly = (string) strtok($path, '?');
        $status = $response->status;

        if ($status === 401) {
            return new SchedulePhotoDomainException('PhotoText: неверный токен (401).', 401);
        }

        if ($status === 422) {
            $message = $this->extractErrorMessage($response->body);

            return new SchedulePhotoDomainException(
                $message !== '' ? $message : 'PhotoText отклонил запрос (422).',
                422,
            );
        }

        if ($status === 403) {
            $message = $this->extractErrorMessage($response->body);

            return new SchedulePhotoDomainException(
                $message !== '' ? $message : 'PhotoText: доступ запрещён (403).',
                403,
            );
        }

        if ($status >= 500 || $status === 0) {
            return new SchedulePhotoDomainException(
                'PhotoText недоступен (HTTP '.$status.') для '.$method.' '.$pathOnly.'.',
                503,
            );
        }

        return new SchedulePhotoDomainException(
            'Ошибка PhotoText HTTP '.$status.' для '.$method.' '.$pathOnly.'.',
            $status >= 400 && $status < 500 ? $status : 502,
        );
    }

    private function extractErrorMessage(string $body): string
    {
        $decoded = json_decode(trim($body), true);
        if (! is_array($decoded)) {
            return '';
        }

        foreach (['message', 'error', 'detail'] as $key) {
            $value = $decoded[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
