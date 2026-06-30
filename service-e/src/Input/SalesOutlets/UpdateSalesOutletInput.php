<?php

declare(strict_types=1);

namespace App\Input\SalesOutlets;

use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Validator\Constraint\ValidHeadOrganizationType;
use App\Validator\Constraint\ValidRussianInn;
use App\Validator\SalesOutletStatusChoices;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Входные данные PATCH /api/sales-outlets/{id}.
 * Валидируется Symfony Validator, затем преобразуется в UpdateSalesOutletDto.
 */
class UpdateSalesOutletInput
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $shop = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $manager = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $curator = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[ValidRussianInn]
    public ?string $inn = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $head_organization = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[ValidHeadOrganizationType]
    public ?string $head_organization_type = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $organization_name = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Choice(callback: [SalesOutletStatusChoices::class, 'values'])]
    public ?string $status = null;

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        $input = new self();
        $input->shop = self::nullableString($payload['shop'] ?? null);
        $input->manager = self::nullableString($payload['manager'] ?? null);
        $input->curator = self::nullableString($payload['curator'] ?? null);
        $input->name = self::nullableString($payload['name'] ?? null);
        $input->inn = self::nullableString($payload['inn'] ?? null);
        $input->head_organization = self::nullableString($payload['head_organization'] ?? null);
        $input->head_organization_type = self::nullableString($payload['head_organization_type'] ?? null);
        $input->organization_name = self::nullableString($payload['organization_name'] ?? null);
        $input->status = self::nullableString($payload['status'] ?? null);

        return $input;
    }

    public function toDto(): UpdateSalesOutletDto
    {
        return UpdateSalesOutletDto::fromValidated([
            'shop' => $this->shop,
            'manager' => $this->manager,
            'curator' => $this->curator,
            'name' => $this->name,
            'inn' => $this->inn,
            'head_organization' => $this->head_organization,
            'head_organization_type' => $this->head_organization_type,
            'organization_name' => $this->organization_name,
            'status' => $this->status,
        ]);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
