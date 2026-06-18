<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\DTO\YandexMaps\ConfirmOrganizationDto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос на подтверждение кандидата из resolve-сессии.
 */
class ConfirmOrganizationRequest extends FormRequest
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
            'session_id' => ['required', 'uuid'],
            'org_id' => ['required', 'string', 'regex:/^\d+$/'],
        ];
    }

    /**
     * Собирает DTO из провалидированных полей запроса.
     */
    public function toDto(): ConfirmOrganizationDto
    {
        return new ConfirmOrganizationDto(
            sessionId: (string) $this->validated('session_id'),
            orgId: (string) $this->validated('org_id'),
        );
    }
}
