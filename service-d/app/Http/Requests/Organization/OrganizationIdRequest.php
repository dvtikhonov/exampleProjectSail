<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Общая валидация organization_id для эндпоинтов организации.
 */
class OrganizationIdRequest extends FormRequest
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
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ];
    }

    /**
     * Идентификатор организации из провалидированного запроса.
     */
    public function organizationId(): int
    {
        return (int) $this->validated('organization_id');
    }
}
