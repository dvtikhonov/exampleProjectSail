<?php

namespace App\Http\Requests\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

class StoreSalesOutletMailRequest extends FormRequest
{
    public function __construct(
        private readonly SalesOutletsExportMetadataRepositoryInterface $metadataRepository,
    ) {}

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedColumns = $this->metadataRepository->allowedColumnKeys();

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_merge([''], array_column(SalesOutletStatus::cases(), 'value')))],
            'column_filters' => ['nullable', 'array'],
            'column_filters.*' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in($allowedColumns)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in($allowedColumns)],
        ];
    }

    public function toDto(): SalesOutletExportFilterDto
    {
        return SalesOutletExportFilterDto::fromValidated(
            validated: $this->validated(),
            allowedColumns: $this->metadataRepository->allowedColumnKeys(),
        );
    }
}
