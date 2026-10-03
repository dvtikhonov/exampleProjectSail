<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация POST admin/briskly-sync/sessions/{id}/match.
 */
class MatchBrisklySyncSessionRequest extends FormRequest
{
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
            'rematch' => ['sometimes', 'boolean'],
        ];
    }

    public function rematch(): bool
    {
        return (bool) ($this->validated('rematch') ?? false);
    }
}
