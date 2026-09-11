<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Requests;

use App\Modules\FoodReport\Enums\ReportType;
use Illuminate\Validation\Rule;

/**
 * Валидация GET /api/food/admin/reports/export.
 *
 * Поля: date_from, date_to, restaurant_id, report_type (обязателен), опц. date_axis, limit.
 */
class ExportFoodReportRequest extends FoodReportFilterRequest
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'report_type' => ['required', 'string', Rule::enum(ReportType::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'report_type.required' => 'Укажите тип отчёта.',
            'report_type.Illuminate\Validation\Rules\Enum' => 'Недопустимый тип отчёта.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'report_type' => 'тип отчёта',
            'limit' => 'лимит позиций',
        ]);
    }

    public function reportType(): ReportType
    {
        return ReportType::from((string) $this->validated('report_type'));
    }

    public function limitPerDay(): int
    {
        $value = $this->validated('limit');

        return $value !== null ? (int) $value : self::DEFAULT_LIMIT;
    }
}
