<?php

declare(strict_types=1);

namespace App\Input\SalesOutlets;

use App\Contract\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\Validator\Constraint\InAllowedSalesOutletColumn;
use App\Validator\SalesOutletStatusChoices;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Входные данные GET /api/sales-outlets.
 * Валидируется Symfony Validator, затем преобразуется в SalesOutletIndexQueryDto.
 */
class IndexSalesOutletsInput
{
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $search = null;

    #[Assert\Type('string')]
    #[Assert\Choice(callback: [SalesOutletStatusChoices::class, 'values'])]
    public ?string $status = null;

    /** @var array<string, string|null>|null */
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Length(max: 255),
    ])]
    public ?array $column_filters = null;

    #[Assert\Type('string')]
    #[InAllowedSalesOutletColumn]
    public ?string $sort = null;

    #[Assert\Type('string')]
    #[Assert\Choice(choices: ['asc', 'desc'])]
    public ?string $direction = null;

    /** @var array<int, string>|null */
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('string'),
        new InAllowedSalesOutletColumn(),
    ])]
    public ?array $columns = null;

    #[Assert\Type('integer')]
    public int|string|null $page = null;

    #[Assert\Type('integer')]
    public int|string|null $per_page = null;

    /** Создаёт Input из query-параметров HTTP-запроса. */
    public static function fromRequest(Request $request): self
    {
        $input = new self();
        $query = $request->query->all();

        $input->search = self::nullableString($query['search'] ?? null);
        $input->status = self::nullableString($query['status'] ?? null);
        $input->column_filters = self::columnFilters($query['column_filters'] ?? null);
        $input->sort = self::nullableString($query['sort'] ?? null);
        $input->direction = self::nullableString($query['direction'] ?? null);
        $input->columns = self::stringList($query['columns'] ?? null);
        $input->page = self::nullableInt($query['page'] ?? null);
        $input->per_page = self::nullableInt($query['per_page'] ?? null);

        return $input;
    }

    /** Преобразует валидированный Input в DTO параметров запроса. */
    public function toQueryDto(SalesOutletsMetadataRepositoryInterface $metadataRepository): SalesOutletIndexQueryDto
    {
        return SalesOutletIndexQueryDto::fromValidated(
            $this->toValidatedArray(),
            $metadataRepository->allowedColumnKeys(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toValidatedArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
            'column_filters' => $this->column_filters,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'columns' => $this->columns,
            'page' => $this->page,
            'per_page' => $this->per_page,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @return array<string, string|null>|null
     */
    private static function columnFilters(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $filters = [];

        foreach ($value as $key => $filterValue) {
            $filters[(string) $key] = null === $filterValue ? null : (string) $filterValue;
        }

        return $filters;
    }

    /**
     * @return array<int, string>|null
     */
    private static function stringList(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return array_map(static fn (mixed $item): string => (string) $item, array_values($value));
    }

    private static function nullableString(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }

    private static function nullableInt(mixed $value): int|string|null
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return is_numeric($value) ? (int) $value : (string) $value;
    }
}
