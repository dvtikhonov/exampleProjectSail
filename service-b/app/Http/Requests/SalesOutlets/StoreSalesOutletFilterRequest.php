<?php

namespace App\Http\Requests\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletReportFilterDtoFactoryInterface;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Rules\SalesOutlets\InAllowedSalesOutletColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

class StoreSalesOutletFilterRequest extends FormRequest
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
        $allowedColumnRule = $this->container->make(InAllowedSalesOutletColumn::class);

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_column(SalesOutletStatus::cases(), 'value'))],
            'column_filters' => ['nullable', 'array'],
            'column_filters.*' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', $allowedColumnRule],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', $allowedColumnRule],
        ];
    }

    public function toDto(): SalesOutletReportFilterDto
    {
        return $this->container
            ->make(SalesOutletReportFilterDtoFactoryInterface::class)
            ->fromValidated($this->validated());
    }
}
