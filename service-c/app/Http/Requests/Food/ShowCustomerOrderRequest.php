<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use App\Http\Requests\Food\Concerns\AuthorizesCustomerResourceOwnership;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса деталей заказа клиента.
 *
 * Тело запроса не требуется; ownership — в authorize(), сервис — defense-in-depth.
 */
class ShowCustomerOrderRequest extends FormRequest
{
    use AuthorizesCustomerResourceOwnership;

    /**
     * Разрешает запрос только владельцу заказа (отсутствующий заказ — в сервис → 404).
     */
    public function authorize(): bool
    {
        $user = $this->authenticatedMaxUser();

        if ($user === null) {
            return false;
        }

        $owns = $this->ownershipGuard()->ownsOrder(
            $user,
            $this->routeResourceId('order'),
        );

        return $owns !== false;
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
