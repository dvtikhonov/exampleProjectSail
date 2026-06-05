<?php

namespace App\Http\Requests\SalesOutlets;

use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Rules\SalesOutlets\ValidHeadOrganizationType;
use App\Rules\SalesOutlets\ValidRussianInn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

class UpdateSalesOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop' => ['required', 'string', 'max:255'],
            'manager' => ['required', 'string', 'max:255'],
            'curator' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'inn' => ['required', 'string', new ValidRussianInn()],
            'head_organization' => ['required', 'string', 'max:255'],
            'head_organization_type' => ['required', 'string', new ValidHeadOrganizationType()],
            'organization_name' => ['required', 'string', 'max:255'],
            'status' => [
                'required',
                'string',
                Rule::in(array_column(SalesOutletStatus::cases(), 'value')),
            ],
        ];
    }

    public function toDto(): UpdateSalesOutletDto
    {
        return UpdateSalesOutletDto::fromValidated($this->validated());
    }
}
