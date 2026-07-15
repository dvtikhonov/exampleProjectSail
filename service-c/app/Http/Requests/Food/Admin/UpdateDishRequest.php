<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

/**
 * Валидация multipart-запроса обновления блюда.
 */
class UpdateDishRequest extends BaseDishFormRequest
{
    /**
     * Правила валидации обновления блюда с опциональным фото.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->dishAttributeRules(sometimes: true),
            'photo' => $this->photoRules(required: false),
        ];
    }
}
