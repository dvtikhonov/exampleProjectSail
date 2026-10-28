<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use App\Enums\MessengerChoice;
use Illuminate\Validation\Rule;

/**
 * Валидация POST /internal/messenger-choice/clicks.
 * Поля визита те же, что у страницы. messenger — telegram или max, не ник и не URL бота.
 */
class LogMessengerChoiceClickRequest extends LogMessengerChoiceRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'messenger' => ['required', 'string', Rule::enum(MessengerChoice::class)],
        ];
    }

    public function messenger(): MessengerChoice
    {
        return MessengerChoice::from((string) $this->validated('messenger'));
    }
}
