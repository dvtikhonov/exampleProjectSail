<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\DTO\Food\Menu\MenuCategoryAvailabilityOffsetDto;
use App\Enums\Food\Menu\Weekday;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Общие правила и разбор availability_offsets для store/update категории меню.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ValidatesMenuCategoryAvailabilityOffsets
{
    /**
     * Правила валидации поля availability_offsets.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function availabilityOffsetRules(): array
    {
        return [
            'availability_offsets' => ['sometimes', 'array'],
            'availability_offsets.*.weekdays' => ['required', 'array', 'min:1'],
            'availability_offsets.*.weekdays.*' => ['required', 'integer', Rule::enum(Weekday::class)],
            'availability_offsets.*.offset_days' => ['required', 'integer', 'min:0', 'max:30'],
        ];
    }

    /**
     * Сообщения об ошибках для availability_offsets.
     *
     * @return array<string, string>
     */
    protected function availabilityOffsetMessages(): array
    {
        return [
            'availability_offsets.*.weekdays.required' => 'Укажите хотя бы один день недели.',
            'availability_offsets.*.weekdays.min' => 'Укажите хотя бы один день недели.',
            'availability_offsets.*.weekdays.*.enum' => 'День недели должен быть числом от 1 (Пн) до 7 (Вс).',
            'availability_offsets.*.offset_days.required' => 'Укажите смещение в днях.',
            'availability_offsets.*.offset_days.min' => 'Смещение не может быть меньше 0.',
            'availability_offsets.*.offset_days.max' => 'Смещение не может быть больше 30.',
        ];
    }

    /**
     * Человекочитаемые имена атрибутов availability_offsets.
     *
     * @return array<string, string>
     */
    protected function availabilityOffsetAttributes(): array
    {
        return [
            'availability_offsets' => 'смещения доступности',
            'availability_offsets.*.weekdays' => 'дни недели',
            'availability_offsets.*.weekdays.*' => 'день недели',
            'availability_offsets.*.offset_days' => 'смещение в днях',
        ];
    }

    /**
     * Проверяет, что каждый день недели встречается максимум в одном правиле.
     */
    protected function validateAvailabilityOffsetWeekdayUniqueness(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $offsets = $this->input('availability_offsets');

            if (! is_array($offsets)) {
                return;
            }

            /** @var array<int, int> $seenWeekdayToRuleIndex */
            $seenWeekdayToRuleIndex = [];

            foreach ($offsets as $ruleIndex => $rule) {
                if (! is_array($rule) || ! isset($rule['weekdays']) || ! is_array($rule['weekdays'])) {
                    continue;
                }

                foreach ($rule['weekdays'] as $weekday) {
                    $weekdayValue = (int) $weekday;

                    if (array_key_exists($weekdayValue, $seenWeekdayToRuleIndex)) {
                        $validator->errors()->add(
                            "availability_offsets.{$ruleIndex}.weekdays",
                            'Каждый день недели может быть указан только в одном правиле смещения.',
                        );

                        return;
                    }

                    $seenWeekdayToRuleIndex[$weekdayValue] = (int) $ruleIndex;
                }
            }
        });
    }

    /**
     * Собирает список DTO правил смещения из validated-данных.
     *
     * @param  array<string, mixed>  $validated
     * @return list<MenuCategoryAvailabilityOffsetDto>
     */
    protected function mapAvailabilityOffsetsFromValidated(array $validated): array
    {
        if (! array_key_exists('availability_offsets', $validated) || ! is_array($validated['availability_offsets'])) {
            return [];
        }

        $result = [];

        foreach ($validated['availability_offsets'] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            /** @var list<int> $weekdays */
            $weekdays = array_values(array_map(
                static fn (mixed $day): int => (int) $day,
                $rule['weekdays'] ?? [],
            ));
            sort($weekdays);

            $result[] = new MenuCategoryAvailabilityOffsetDto(
                weekdays: $weekdays,
                offsetDays: (int) ($rule['offset_days'] ?? 0),
            );
        }

        return $result;
    }
}
