<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация PUT admin/briskly-sync/sessions/{id}/approvals.
 *
 * Клиентский price запрещён (rejected); create+apply → briskly_category_id required.
 */
class UpdateBrisklySyncApprovalsRequest extends FormRequest
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
            'price_updates' => ['nullable', 'array', 'max:25'],
            'price_updates.*.line_key' => ['required', 'string', 'max:190'],
            'price_updates.*.apply' => ['required', 'boolean'],
            'price_updates.*.confirm_large_delta' => ['sometimes', 'boolean'],
            'price_updates.*.price' => ['prohibited'],
            'creates' => ['nullable', 'array', 'max:25'],
            'creates.*.line_key' => ['required', 'string', 'max:190'],
            'creates.*.apply' => ['required', 'boolean'],
            'creates.*.briskly_category_id' => ['nullable', 'integer', 'min:1'],
            'creates.*.price' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_updates.*.price.prohibited' => 'Клиентский price запрещён.',
            'creates.*.price.prohibited' => 'Клиентский price запрещён.',
            'price_updates.max' => 'Не более 25 позиций UPDATE.',
            'creates.max' => 'Не более 25 позиций CREATE.',
        ];
    }

    /**
     * @return array{price_updates: list<array<string, mixed>>, creates: list<array<string, mixed>>}
     */
    public function approvalsPayload(): array
    {
        $validated = $this->validated();

        return [
            'price_updates' => array_values($validated['price_updates'] ?? []),
            'creates' => array_values($validated['creates'] ?? []),
        ];
    }
}
