<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Requests\Food\Concerns\AuthorizesCustomerResourceOwnership;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса удаления позиции корзины.
 *
 * Тело запроса не требуется; ownership — в authorize(), сервис — defense-in-depth.
 */
class DestroyCartItemRequest extends FormRequest
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
     * Всегда ожидает JSON-ответ.
     */
    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * Тело запроса пустое.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
