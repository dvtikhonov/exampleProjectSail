<?php

namespace App\Http\Requests\SalesOutlets;

use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\Rules\SalesOutlets\ValidHeadOrganizationType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHeadOrganizationRequest extends FormRequest
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
            'head_organization' => ['required', 'string', 'max:255'],
            'head_organization_type' => ['required', 'string', new ValidHeadOrganizationType()],
        ];
    }

    public function toDto(): UpdateHeadOrganizationDto
    {
        return UpdateHeadOrganizationDto::fromValidated($this->validated());
    }
}
