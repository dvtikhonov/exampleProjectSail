<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Internal;

use App\DTO\Food\BrisklySync\MatchLineResultDto;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация POST /api/food/internal/briskly-sync/match-complete (колбэк sidecar).
 *
 * Поля: session_id, match_generation (uuid); XOR match_lines[] vs error; raw_text опционален.
 */
class CompleteBrisklySyncMatchRequest extends FormRequest
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
            'session_id' => ['required', 'uuid'],
            'match_generation' => ['required', 'uuid'],
            'match_lines' => ['nullable', 'array'],
            'match_lines.*.line_key' => ['required', 'string', 'max:190'],
            'match_lines.*.display_name' => ['required', 'string', 'max:500'],
            'match_lines.*.compare_name' => ['required', 'string', 'max:500'],
            'match_lines.*.candidates' => ['nullable', 'array'],
            'match_lines.*.candidates.*.id' => ['required', 'integer', 'min:1'],
            'match_lines.*.candidates.*.name' => ['required', 'string', 'max:500'],
            'match_lines.*.price' => ['prohibited'],
            'match_lines.*.candidates.*.price' => ['prohibited'],
            'raw_text' => ['nullable', 'string'],
            'error' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'match_lines.*.price.prohibited' => 'Цена от LLM запрещена в match_lines.',
            'match_lines.*.candidates.*.price.prohibited' => 'Цена от LLM запрещена в candidates.',
        ];
    }

    /**
     * XOR: ровно одно из match_lines (array) или непустого error.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $error = $this->input('error');
            $hasError = is_string($error) && trim($error) !== '';
            $hasLines = is_array($this->input('match_lines'));

            if ($hasError === $hasLines) {
                $validator->errors()->add(
                    'match_lines',
                    'Нужны либо match_lines, либо error.',
                );
            }
        });
    }

    public function sessionId(): string
    {
        return (string) $this->validated('session_id');
    }

    public function matchGeneration(): string
    {
        return (string) $this->validated('match_generation');
    }

    /**
     * @return list<MatchLineResultDto>|null
     */
    public function matchLines(): ?array
    {
        if ($this->error() !== null) {
            return null;
        }

        $raw = $this->validated('match_lines');
        if (! is_array($raw)) {
            return null;
        }

        $lines = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $lines[] = MatchLineResultDto::fromArray($row);
            }
        }

        return $lines;
    }

    public function error(): ?string
    {
        $error = $this->validated('error') ?? null;
        if (! is_string($error)) {
            return null;
        }

        $trimmed = trim($error);

        return $trimmed !== '' ? $trimmed : null;
    }
}
