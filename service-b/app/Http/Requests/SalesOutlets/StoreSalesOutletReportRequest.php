<?php

namespace App\Http\Requests\SalesOutlets;

use App\Enums\SalesOutletsReportType;
use Illuminate\Validation\Rule;

class StoreSalesOutletReportRequest extends StoreSalesOutletFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'report_type' => ['required', 'string', Rule::enum(SalesOutletsReportType::class)],
        ];
    }

    public function toReportType(): SalesOutletsReportType
    {
        return SalesOutletsReportType::from($this->validated('report_type'));
    }
}
