<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\Enums\Food\Order\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query-параметров списка ручных заказов (потребитель, период, статус и/или ФИО).
 */
class ListManualOrdersRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    /**
     * Разрешает любой запрос.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Всегда ожидает JSON-ответ.
     */
    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * Правила валидации фильтров списка ручных заказов.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'max_user_id' => ['nullable', 'integer', 'min:1', 'exists:max_users,max_user_id'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    OrderStatus::DraftAfterScanning->value,
                    OrderStatus::PendingReview->value,
                    OrderStatus::AwaitingComposition->value,
                    OrderStatus::Confirmed->value,
                    OrderStatus::Rejected->value,
                ]),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * Сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
            'status.in' => 'Недопустимый статус заказа.',
        ];
    }

    /**
     * Человекочитаемые имена атрибутов.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => 'ФИО',
            'max_user_id' => 'потребитель',
            'date_from' => 'дата начала',
            'date_to' => 'дата окончания',
            'status' => 'статус',
            'per_page' => 'размер страницы',
        ];
    }

    /**
     * Нормализованная строка поиска по ФИО или null.
     */
    public function searchQuery(): ?string
    {
        $value = $this->validated('q');

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * max_user_id выбранного потребителя или null.
     */
    public function customerMaxUserId(): ?int
    {
        $value = $this->validated('max_user_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Дата начала периода (Y-m-d) или null.
     */
    public function dateFrom(): ?string
    {
        $value = $this->validated('date_from');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Дата окончания периода (Y-m-d) или null.
     */
    public function dateTo(): ?string
    {
        $value = $this->validated('date_to');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Фильтр статуса заказа или null (все статусы).
     */
    public function status(): ?OrderStatus
    {
        $value = $this->validated('status');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return OrderStatus::from($value);
    }

    /**
     * Размер страницы списка заказов.
     */
    public function perPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : self::DEFAULT_PER_PAGE;
    }
}
