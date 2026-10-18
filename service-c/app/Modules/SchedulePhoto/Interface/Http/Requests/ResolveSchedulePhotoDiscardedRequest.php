<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Interface\Http\Requests;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoResolveDiscardedDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация POST .../runs/{runId}/resolve-discarded.
 *
 * Обязательно: raw_title, dates[] (Y-m-d). dish_name — опционально
 * (если пусто — канон из словаря алиасов на сервере).
 */
class ResolveSchedulePhotoDiscardedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'raw_title' => ['required', 'string', 'max:500'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['required', 'date_format:Y-m-d'],
            'dish_name' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'raw_title.required' => 'Укажите raw_title отброшенной строки.',
            'dates.required' => 'Укажите хотя бы одну дату в окне графика.',
            'dates.min' => 'Укажите хотя бы одну дату в окне графика.',
            'dates.*.date_format' => 'Даты должны быть в формате Y-m-d.',
        ];
    }

    public function toDto(string $runId): SchedulePhotoResolveDiscardedDto
    {
        /** @var array{raw_title: string, dates: list<string>, dish_name?: string|null} $validated */
        $validated = $this->validated();

        $dishName = isset($validated['dish_name']) && is_string($validated['dish_name'])
            ? trim($validated['dish_name'])
            : null;
        if ($dishName === '') {
            $dishName = null;
        }

        /** @var list<string> $dates */
        $dates = array_values(array_unique(array_map(
            static fn (string $d): string => trim($d),
            $validated['dates'],
        )));

        return new SchedulePhotoResolveDiscardedDto(
            runId: $runId,
            rawTitle: trim($validated['raw_title']),
            dates: $dates,
            dishName: $dishName,
        );
    }
}
