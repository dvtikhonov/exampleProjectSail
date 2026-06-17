<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\DTO\YandexMaps\ResolveOrganizationDto;
use App\Services\YandexMaps\OrganizationSearchInputValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        /** @var OrganizationSearchInputValidator $validator */
        $validator = app(OrganizationSearchInputValidator::class);

        return [
            'url' => $validator->validationRules(),
        ];
    }

    public function toDto(): ResolveOrganizationDto
    {
        /** @var OrganizationSearchInputValidator $validator */
        $validator = app(OrganizationSearchInputValidator::class);
        $input = $validator->parse((string) $this->validated('url'));

        if ($input === null) {
            throw new \InvalidArgumentException('Organization search input is invalid after validation.');
        }

        return new ResolveOrganizationDto(
            inputUrl: $input->rawInput,
            resolverUrl: $validator->toResolverUrl($input),
            searchText: $input->mapsSearchQuery(),
            clarification: $input->clarification,
        );
    }
}
