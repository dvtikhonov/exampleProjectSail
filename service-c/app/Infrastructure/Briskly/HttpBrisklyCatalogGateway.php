<?php

declare(strict_types=1);

namespace App\Infrastructure\Briskly;

use App\Contracts\Food\BrisklySync\BrisklyCatalogGatewayInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Food\BrisklySync\BrisklyCategoryDto;
use App\DTO\Food\BrisklySync\BrisklyCreatedItemDto;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\BrisklySync\BrisklySyncBearerToken;
use App\Services\Food\BrisklySync\BrisklySyncPrice;

/**
 * HTTP-адаптер к Briskly company API (SSL verify через Http-клиент).
 */
final class HttpBrisklyCatalogGateway implements BrisklyCatalogGatewayInterface
{
    /** ОКЕИ «Штука» — дефолт create в кабинете Briskly (unit_796). */
    private const int DEFAULT_CREATE_UNIT_ID = 796;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
        private readonly int $pageLimit,
        private readonly int $maxPages,
        private readonly int $delayMs,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function fetchSnapshot(string $token, ?string $searchText): array
    {
        $all = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = $this->requestJson(
                $token,
                'GET',
                '/v2/dashboard/item/get-list?'.$this->buildItemListQuery($page, $this->pageLimit),
            );
            $items = is_array($response['items'] ?? null) ? $response['items'] : [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $name = (string) ($item['name'] ?? '');
                if (! $this->matchesSearch($name, $searchText)) {
                    continue;
                }
                $all[] = BrisklySnapshotItemDto::fromArray([
                    'id' => $item['id'] ?? 0,
                    'name' => $name,
                    'price' => $item['price'] ?? '0',
                ]);
            }

            $meta = is_array($response['meta'] ?? null) ? $response['meta'] : [];
            $totalPages = (int) ($meta['total_pages'] ?? $page);
            $page++;
            if ($page <= $totalPages && $page <= $this->maxPages && $this->delayMs > 0) {
                usleep($this->delayMs * 1000);
            }
        } while ($page <= $totalPages && $page <= $this->maxPages);

        return $all;
    }

    /**
     * {@inheritDoc}
     */
    public function listCategories(string $token): array
    {
        $all = [];
        $page = 1;
        $totalPages = 1;
        $limit = 200;

        do {
            $qs = http_build_query([
                'page' => $page,
                'limit' => $limit,
                'fields' => [
                    'id' => 'id',
                    'name' => 'name',
                    'catalog_id' => 'catalog_id',
                    'parent_id' => 'parent_id',
                    'status' => 'status',
                ],
            ]);
            $response = $this->requestJson(
                $token,
                'GET',
                '/v2/dashboard/category/get-list?'.$qs,
            );
            $items = is_array($response['items'] ?? null) ? $response['items'] : [];
            foreach ($items as $item) {
                if (is_array($item)) {
                    $all[] = BrisklyCategoryDto::fromArray($item);
                }
            }

            $meta = is_array($response['meta'] ?? null) ? $response['meta'] : [];
            $totalPages = (int) ($meta['total_pages'] ?? $page);
            $page++;
            if ($page <= $totalPages && $this->delayMs > 0) {
                usleep($this->delayMs * 1000);
            }
        } while ($page <= $totalPages);

        return $all;
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemPrice(string $token, int $itemId, string $price): void
    {
        $item = $this->requestJson(
            $token,
            'GET',
            '/v1/dashboard/item/get-by-id?'.http_build_query(['id' => $itemId]),
        );

        $payload = $this->buildUpdatePricePayload($item, $price);
        $this->requestJson($token, 'POST', '/v2/dashboard/item/update', $payload);
    }

    /**
     * {@inheritDoc}
     */
    public function createItem(
        string $token,
        string $name,
        string $price,
        int $categoryId,
        int $catalogId,
    ): BrisklyCreatedItemDto {
        $payload = [
            'name' => trim($name),
            'catalog_id' => $catalogId,
            'category_id' => $categoryId,
            'parent_id' => 0,
            'barcode' => 'generate',
            'barcodes' => [],
            'cost' => 0,
            'price' => (float) BrisklySyncPrice::normalize($price),
            'modifications' => [],
            'vat_mode' => 0,
            'vat_rate' => 0,
            'unit_id' => self::DEFAULT_CREATE_UNIT_ID,
            'unit_dimension' => 0,
            'file' => null,
            'status' => 1,
            'extra_code_type' => 0,
            'age_limit' => 0,
            'heating_enabled' => 0,
            'heating_duration' => 0,
            'heating_power' => 0,
            'sticker_enabled' => 1,
            'article' => '',
            'text' => '',
            'suggested_item_ids' => [],
            'props' => [],
        ];

        $response = $this->requestJson($token, 'POST', '/v1/dashboard/item/create', $payload);

        return BrisklyCreatedItemDto::fromArray($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(
        string $token,
        string $method,
        string $path,
        ?array $jsonBody = null,
    ): array {
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer '.BrisklySyncBearerToken::normalize($token),
            'Accept-Language' => 'ru',
        ];

        $response = $this->http->request(
            $method,
            $path,
            $headers,
            $jsonBody,
            $this->baseUrl,
            $this->timeoutSeconds,
        );

        if (! $response->successful) {
            $detail = $this->brisklyErrorDetail($response->body);
            throw new FoodDomainException(
                'Ошибка Briskly HTTP '.$response->status.' для '.$method.' '.strtok($path, '?').$detail,
                $response->status >= 500 ? 502 : 422,
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function buildItemListQuery(int $page, int $limit): string
    {
        return http_build_query([
            'page' => $page,
            'limit' => $limit,
            'fields' => [
                'id' => 'id',
                'category' => 'category.name',
                'name' => 'name',
                'price' => 'price',
                'quantity_calculated' => 'quantity_calculated',
                'status' => 'status',
                'catalog_id' => 'catalog_id',
                'category_id' => 'category_id',
            ],
            'filters' => [
                'parent_id' => ['0'],
                '!catalog.item_type' => ['coffee'],
            ],
        ]);
    }

    private function matchesSearch(string $name, ?string $searchText): bool
    {
        $needle = $searchText !== null ? mb_strtolower(trim($searchText)) : '';
        if ($needle === '') {
            return true;
        }

        return str_contains(mb_strtolower($name), $needle);
    }

    /**
     * Короткий текст ошибки из тела Briskly (без токена/секретов).
     */
    private function brisklyErrorDetail(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            foreach (['message', 'error', 'detail', 'title'] as $key) {
                $value = $decoded[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return ' '.$this->truncateDetail(trim($value));
                }
            }

            $errors = $decoded['errors'] ?? null;
            if (is_array($errors) && $errors !== []) {
                $parts = [];
                foreach ($errors as $field => $messages) {
                    if (is_string($messages) && trim($messages) !== '') {
                        $parts[] = is_string($field) ? $field.': '.$messages : $messages;

                        continue;
                    }
                    if (! is_array($messages)) {
                        continue;
                    }
                    foreach ($messages as $message) {
                        if (is_string($message) && trim($message) !== '') {
                            $parts[] = is_string($field)
                                ? $field.': '.$message
                                : $message;
                        }
                    }
                }
                if ($parts !== []) {
                    return ' '.$this->truncateDetail(implode('; ', $parts));
                }
            }
        }

        return ' '.$this->truncateDetail($trimmed);
    }

    private function truncateDetail(string $detail): string
    {
        if (mb_strlen($detail) <= 280) {
            return $detail;
        }

        return mb_substr($detail, 0, 277).'...';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function buildUpdatePricePayload(array $item, string $newPrice): array
    {
        if (! isset($item['id'], $item['catalog_id'], $item['category_id'], $item['name'])) {
            throw new FoodDomainException('Неполный ответ get-by-id Briskly.', 502);
        }

        return [
            'id' => (int) $item['id'],
            'name' => (string) $item['name'],
            'catalog_id' => (int) $item['catalog_id'],
            'category_id' => (int) $item['category_id'],
            'barcode' => (string) ($item['barcode'] ?? ''),
            'barcodes' => is_array($item['barcodes'] ?? null) ? $item['barcodes'] : [],
            'cost' => $item['cost'] ?? 0,
            'price' => (float) BrisklySyncPrice::normalize($newPrice),
            'modifications' => is_array($item['modifications'] ?? null) ? $item['modifications'] : [],
            'vat_mode' => (int) ($item['vat_mode'] ?? 0),
            'vat_rate' => (int) ($item['vat_rate'] ?? 0),
            'unit_id' => (int) ($item['unit_id'] ?? 0),
            'unit_dimension' => (int) ($item['unit_dimension'] ?? 0),
            'file' => null,
            'status' => (int) ($item['status'] ?? 1),
            'extra_code_type' => (int) ($item['extra_code_type'] ?? 0),
            'age_limit' => (int) ($item['age_limit'] ?? 0),
            'heating_enabled' => (int) ($item['heating_enabled'] ?? 0),
            'heating_duration' => (int) ($item['heating_duration'] ?? 0),
            'heating_power' => (int) ($item['heating_power'] ?? 0),
            'sticker_enabled' => (int) ($item['sticker_enabled'] ?? 1),
            'article' => (string) ($item['article'] ?? ''),
            'suggested_item_ids' => is_array($item['suggested_item_ids'] ?? null)
                ? array_map('intval', $item['suggested_item_ids'])
                : [],
            'props' => is_array($item['props'] ?? null) ? $item['props'] : [],
        ];
    }
}
