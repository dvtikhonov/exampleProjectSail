<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос на получение карточки организации; organization_id необязателен.
 */
class ShowOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['sometimes', 'nullable', 'integer', 'exists:organizations,id'],
        ];
    }

    /**
     * Идентификатор организации или null, если параметр не передан.
     */
    public function organizationId(): ?int
    {
        $value = $this->validated('organization_id');

        return $value !== null ? (int) $value : null;
    }
}
