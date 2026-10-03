<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Набор approvals (без клиентского price).
 */
readonly class BrisklySyncApprovalsDto
{
    /**
     * @param  list<PriceUpdateApprovalDto>  $priceUpdates
     * @param  list<CreateApprovalDto>  $creates
     */
    public function __construct(
        public array $priceUpdates,
        public array $creates,
    ) {}

    /**
     * @param  array{price_updates?: mixed, creates?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $priceUpdates = [];
        foreach ($data['price_updates'] ?? [] as $item) {
            if (is_array($item)) {
                $priceUpdates[] = PriceUpdateApprovalDto::fromArray($item);
            }
        }

        $creates = [];
        foreach ($data['creates'] ?? [] as $item) {
            if (is_array($item)) {
                $creates[] = CreateApprovalDto::fromArray($item);
            }
        }

        return new self($priceUpdates, $creates);
    }

    /**
     * @return array{
     *     price_updates: list<array{line_key: string, apply: bool, confirm_large_delta: bool}>,
     *     creates: list<array{line_key: string, apply: bool, briskly_category_id: int|null}>
     * }
     */
    public function toArray(): array
    {
        return [
            'price_updates' => array_map(
                static fn (PriceUpdateApprovalDto $item): array => $item->toArray(),
                $this->priceUpdates,
            ),
            'creates' => array_map(
                static fn (CreateApprovalDto $item): array => $item->toArray(),
                $this->creates,
            ),
        ];
    }
}
