<?php

namespace App\DTO\SalesOutlets;

use App\Enums\HeadOrganizationType;
use App\Enums\SalesOutletStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

readonly class UpdateSalesOutletDto
{
    public function __construct(
        public string $shop,
        public string $manager,
        public string $curator,
        public string $name,
        public string $inn,
        public string $headOrganization,
        public HeadOrganizationType $headOrganizationType,
        public string $organizationName,
        public SalesOutletStatus $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $payload = $request->json()->all() ?: $request->all();

        $validated = Validator::make($payload, [
            'shop' => ['required', 'string', 'max:255'],
            'manager' => ['required', 'string', 'max:255'],
            'curator' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'inn' => ['required', 'string', 'regex:/^\d{10}(\d{2})?$/'],
            'head_organization' => ['required', 'string', 'max:255'],
            'head_organization_type' => [
                'required',
                'string',
                Rule::in(self::allowedHeadOrganizationTypes()),
            ],
            'organization_name' => ['required', 'string', 'max:255'],
            'status' => [
                'required',
                'string',
                Rule::in(array_column(SalesOutletStatus::cases(), 'value')),
            ],
        ])->validate();

        return new self(
            shop: trim($validated['shop']),
            manager: trim($validated['manager']),
            curator: trim($validated['curator']),
            name: trim($validated['name']),
            inn: trim($validated['inn']),
            headOrganization: trim($validated['head_organization']),
            headOrganizationType: HeadOrganizationType::fromLabelOrValue($validated['head_organization_type']),
            organizationName: trim($validated['organization_name']),
            status: SalesOutletStatus::from($validated['status']),
        );
    }

    /**
     * @return array<int, string>
     */
    private static function allowedHeadOrganizationTypes(): array
    {
        $values = [];

        foreach (HeadOrganizationType::cases() as $type) {
            $values[] = $type->value;
            $values[] = $type->label();
        }

        return $values;
    }
}
