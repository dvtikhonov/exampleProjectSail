<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Requests;

use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\Enums\ReportDateAxis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Базовая валидация фильтра отчётов Food (период, ресторан, ось даты).
 */
abstract class FoodReportFilterRequest extends FormRequest
{
    public const MAX_SPAN_DAYS = 93;

    public function authorize(): bool
    {
        return true;
    }

    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'restaurant_id' => ['required', 'integer', 'min:1', 'exists:max_restaurants,id'],
            'date_axis' => ['nullable', 'string', Rule::enum(ReportDateAxis::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
            'restaurant_id.exists' => 'Указанный ресторан не найден.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'дата начала',
            'date_to' => 'дата окончания',
            'restaurant_id' => 'ресторан',
            'date_axis' => 'ось даты',
        ];
    }

    /**
     * Доп. проверка: период не длиннее MAX_SPAN_DAYS.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('date_from');
            $to = $this->input('date_to');

            if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
                return;
            }

            try {
                $fromDate = new \DateTimeImmutable($from);
                $toDate = new \DateTimeImmutable($to);
            } catch (\Exception) {
                return;
            }

            $spanDays = (int) $fromDate->diff($toDate)->days;

            if ($spanDays > self::MAX_SPAN_DAYS) {
                $validator->errors()->add(
                    'date_to',
                    'Период отчёта не может превышать '.self::MAX_SPAN_DAYS.' дня.',
                );
            }
        });
    }

    public function toFilterDto(): ReportFilterDto
    {
        $axis = $this->validated('date_axis');

        return new ReportFilterDto(
            dateFrom: (string) $this->validated('date_from'),
            dateTo: (string) $this->validated('date_to'),
            restaurantId: (int) $this->validated('restaurant_id'),
            dateAxis: is_string($axis) && $axis !== ''
                ? ReportDateAxis::from($axis)
                : ReportDateAxis::DeliveryDate,
        );
    }
}
