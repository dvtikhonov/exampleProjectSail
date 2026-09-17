<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Requests\Food\Concerns\AuthorizesCustomerResourceOwnership;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса изменения количества позиции корзины.
 */
class UpdateCartItemRequest extends FormRequest
{
    use AuthorizesCustomerResourceOwnership;

    /**
     * Разрешает только владельцу позиции; чужая/отсутствующая — 404 (как в сервисе).
     *
     * @throws FoodDomainException
     */
    public function authorize(): bool
    {
        $user = $this->authenticatedMaxUser();

        if ($user === null) {
            return false;
        }

        if (! $this->ownershipGuard()->ownsCartItem($user, $this->routeResourceId('item'))) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        return true;
    }

    /**
     * Правила валидации количества позиции.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * Возвращает новое количество из валидированных данных.
     */
    public function quantity(): int
    {
        return (int) $this->validated('quantity');
    }
}
