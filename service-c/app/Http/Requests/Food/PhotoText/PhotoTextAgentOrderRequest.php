<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация тела match/place агента PhotoText (без цен, без серверного split комбо).
 */
class PhotoTextAgentOrderRequest extends FormRequest
{
    /**
     * Разрешает выполнение запроса.
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
     * Правила: клиент, дата Y-m-d, активный ресторан, позиции с каноническим именем.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_query' => ['required', 'string', 'max:255'],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'restaurant_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('max_restaurants', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.combo_ref' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Ключ поиска клиента.
     */
    public function customerQuery(): string
    {
        return trim((string) $this->validated('customer_query'));
    }

    /**
     * Дата заказа из шапки промпта (Y-m-d), не с бланка.
     */
    public function orderDate(): string
    {
        return (string) $this->validated('order_date');
    }

    /**
     * Идентификатор активного ресторана.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }

    /**
     * Канонические позиции: одна строка = одно блюдо; комбо только через combo_ref.
     *
     * @return list<PhotoTextAgentItemDto>
     */
    public function items(): array
    {
        /** @var list<array{name: string, quantity: int, combo_ref?: string|null}> $items */
        $items = $this->validated('items');

        return array_values(array_map(
            static function (array $item): PhotoTextAgentItemDto {
                $comboRef = $item['combo_ref'] ?? null;

                return new PhotoTextAgentItemDto(
                    name: trim((string) $item['name']),
                    quantity: (int) $item['quantity'],
                    comboRef: is_string($comboRef) && trim($comboRef) !== '' ? $comboRef : null,
                );
            },
            $items,
        ));
    }
}
