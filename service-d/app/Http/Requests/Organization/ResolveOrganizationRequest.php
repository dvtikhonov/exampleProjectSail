<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Services\YandexMaps\OrganizationSearchInputValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос на разрешение организации по URL или текстовому поисковому запросу.
 */
class ResolveOrganizationRequest extends FormRequest
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
            'url' => (new OrganizationSearchInputValidator)->validationRules(),
        ];
    }
}
