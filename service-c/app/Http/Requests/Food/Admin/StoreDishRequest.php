<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

/**
 * Валидация multipart-запроса создания блюда.
 */
class StoreDishRequest extends BaseDishFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->dishAttributeRules(),
            'photo' => $this->photoRules(required: true),
        ];
    }
}
