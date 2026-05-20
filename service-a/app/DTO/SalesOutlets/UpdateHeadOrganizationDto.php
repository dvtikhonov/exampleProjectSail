<?php

namespace App\DTO\SalesOutlets;

use App\Enums\HeadOrganizationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

readonly class UpdateHeadOrganizationDto
{
    public function __construct(
        public string $headOrganization,
        public HeadOrganizationType $headOrganizationType,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $payload = $request->json()->all() ?: $request->all();

        $validated = Validator::make($payload, [
            'head_organization' => ['required', 'string', 'max:256'],
            'head_organization_type' => [
                'required',
                'string',
                Rule::in(self::allowedTypes()),
            ],
        ])->validate();

        return new self(
            headOrganization: trim($validated['head_organization']),
            headOrganizationType: HeadOrganizationType::fromLabelOrValue($validated['head_organization_type']),
        );
    }

    /**
     * @return array<int, string>
     */
    private static function allowedTypes(): array
    {
        $values = [];

        foreach (HeadOrganizationType::cases() as $type) {
            $values[] = $type->value;
            $values[] = $type->label();
        }

        return $values;
    }
}
