<?php

declare(strict_types=1);

namespace App\Http\Requests\Max;

use Illuminate\Foundation\Http\FormRequest;

class ValidateInitDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'init_data' => ['required', 'string', 'min:1'],
        ];
    }

    public function initData(): string
    {
        return (string) $this->validated('init_data');
    }
}
