<?php

declare(strict_types=1);

namespace App\Infrastructure\Briskly;

use App\Contracts\Food\BrisklySync\BrisklySyncVpsCatalogPortInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Food\BrisklySync\BrisklySyncVpsNamedItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Exceptions\Food\FoodDomainException;

/**
 * HTTP-адаптер source VPS через PhotoText API на remote origin (prod).
 */
final class HttpBrisklySyncVpsCatalogGateway implements BrisklySyncVpsCatalogPortInterface
{
    private const string AGENT_TOKEN_HEADER = 'X-PhotoText-Token';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl,
        private readonly string $agentToken,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listRestaurants(): array
    {
        $payload = $this->requestJson('GET', '/api/food/phototext/restaurants');
        $rows = is_array($payload['restaurants'] ?? null) ? $payload['restaurants'] : [];

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = BrisklySyncVpsNamedItemDto::fromArray($row);
            if ($item->id > 0) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * {@inheritDoc}
     */
    public function listVpsCategories(int $restaurantId): array
    {
        $this->assertRestaurantActive($restaurantId);

        $qs = http_build_query(['restaurant_id' => $restaurantId]);
        $payload = $this->requestJson('GET', '/api/food/phototext/catalog?'.$qs);
        $catalog = is_array($payload['catalog'] ?? null) ? $payload['catalog'] : [];
        $rows = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = BrisklySyncVpsNamedItemDto::fromArray($row);
            if ($item->id > 0) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * {@inheritDoc}
     */
    public function assertRestaurantActive(int $restaurantId): void
    {
        foreach ($this->listRestaurants() as $restaurant) {
            if ($restaurant->id === $restaurantId) {
                return;
            }
        }

        throw new FoodDomainException('Ресторан не найден или неактивен.', 422);
    }

    /**
     * {@inheritDoc}
     */
    public function assertCategoryBelongs(int $restaurantId, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $this->assertRestaurantActive($restaurantId);

        $qs = http_build_query(['restaurant_id' => $restaurantId]);
        $payload = $this->requestJson('GET', '/api/food/phototext/catalog?'.$qs);
        $catalog = is_array($payload['catalog'] ?? null) ? $payload['catalog'] : [];
        $rows = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['id'] ?? 0) === $categoryId) {
                return;
            }
        }

        throw new FoodDomainException('Категория меню не найдена для выбранного ресторана.', 422);
    }

    /**
     * {@inheritDoc}
     */
    public function collectSourceLines(
        int $restaurantId,
        ?int $vpsCategoryId = null,
        ?string $searchText = null,
    ): array {
        $query = ['restaurant_id' => $restaurantId];
        if ($vpsCategoryId !== null) {
            $query['vps_category_id'] = $vpsCategoryId;
        }
        $needle = $searchText !== null ? trim($searchText) : '';
        if ($needle !== '') {
            $query['search_text'] = $needle;
        }

        $payload = $this->requestJson(
            'GET',
            '/api/food/phototext/briskly-source-lines?'.http_build_query($query),
        );
        $rows = is_array($payload['source_lines'] ?? null) ? $payload['source_lines'] : [];

        $lines = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $line = $this->mapSourceLine($row);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapSourceLine(array $row): ?SourceMenuLineDto
    {
        $lineKey = trim((string) ($row['line_key'] ?? ''));
        $typeRaw = (string) ($row['type'] ?? '');
        $type = DailyMenuLineType::tryFrom($typeRaw);
        if ($lineKey === '' || $type === null) {
            return null;
        }

        $partDishIds = [];
        $rawParts = $row['part_dish_ids'] ?? [];
        if (is_array($rawParts)) {
            foreach ($rawParts as $partId) {
                $partDishIds[] = (int) $partId;
            }
        }

        return new SourceMenuLineDto(
            lineKey: $lineKey,
            type: $type,
            displayName: (string) ($row['display_name'] ?? ''),
            price: (string) ($row['price'] ?? '0'),
            partDishIds: $partDishIds,
            brisklyCreateName: (string) ($row['briskly_create_name'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path): array
    {
        $token = trim($this->agentToken);
        if ($token === '') {
            throw new FoodDomainException(
                'Remote Briskly source недоступен: PHOTOTEXT_AGENT_TOKEN не задан.',
                503,
            );
        }

        $baseUrl = rtrim(trim($this->baseUrl), '/');
        if ($baseUrl === '') {
            throw new FoodDomainException(
                'Remote Briskly source недоступен: source_base_url пуст.',
                503,
            );
        }

        $response = $this->http->request(
            $method,
            $path,
            [
                'Accept' => 'application/json',
                self::AGENT_TOKEN_HEADER => $token,
            ],
            null,
            $baseUrl,
            $this->timeoutSeconds,
        );

        if (! $response->successful) {
            throw $this->mapHttpFailure($response, $method, $path);
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new FoodDomainException(
                'Remote Briskly source вернул некорректный JSON.',
                502,
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function mapHttpFailure(HttpResponseDto $response, string $method, string $path): FoodDomainException
    {
        $pathOnly = (string) strtok($path, '?');
        $status = $response->status;

        if ($status === 403) {
            return new FoodDomainException(
                'Remote Briskly source запрещён (403): нужен активный доступ AI на prod.',
                403,
            );
        }

        if ($status === 401) {
            return new FoodDomainException(
                'Remote Briskly source: неверный X-PhotoText-Token.',
                401,
            );
        }

        if ($status === 422) {
            $message = $this->extractErrorMessage($response->body);

            return new FoodDomainException(
                $message !== ''
                    ? $message
                    : 'Remote Briskly source отклонил запрос (422).',
                422,
            );
        }

        if ($status >= 500 || $status === 0) {
            return new FoodDomainException(
                'Remote Briskly source недоступен (HTTP '.$status.') для '.$method.' '.$pathOnly.'.',
                503,
            );
        }

        return new FoodDomainException(
            'Ошибка Remote Briskly source HTTP '.$status.' для '.$method.' '.$pathOnly.'.',
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
