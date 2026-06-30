<?php

declare(strict_types=1);

namespace App\Input\SalesOutlets;

use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\Validator\Constraint\ValidHeadOrganizationType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Входные данные POST /api/sales-outlets/{id}/head-organization.
 * Валидируется Symfony Validator, затем преобразуется в UpdateHeadOrganizationDto.
 */
class UpdateHeadOrganizationInput
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $head_organization = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[ValidHeadOrganizationType]
    public ?string $head_organization_type = null;

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        $input = new self();
        $input->head_organization = isset($payload['head_organization']) ? (string) $payload['head_organization'] : null;
        $input->head_organization_type = isset($payload['head_organization_type']) ? (string) $payload['head_organization_type'] : null;

        return $input;
    }

    public function toDto(): UpdateHeadOrganizationDto
    {
        return UpdateHeadOrganizationDto::fromValidated([
            'head_organization' => $this->head_organization,
            'head_organization_type' => $this->head_organization_type,
        ]);
    }
}
