<?php

namespace App\Http\Requests\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\Rules\SalesOutlets\InAllowedSalesOutletColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

class IndexSalesOutletsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(InAllowedSalesOutletColumn $allowedColumnRule): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_column(SalesOutletStatus::cases(), 'value'))],
            'column_filters' => ['nullable', 'array'],
            'column_filters.*' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', $allowedColumnRule],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', $allowedColumnRule],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function toQueryDto(SalesOutletsMetadataRepositoryInterface $metadataRepository): SalesOutletIndexQueryDto
    {
        return SalesOutletIndexQueryDto::fromValidated(
            $this->validated(),
            $metadataRepository->allowedColumnKeys(),
        );
    }
}
