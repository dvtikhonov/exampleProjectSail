<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Валидация тела match/apply графика производства PhotoText (ровно 7 дней).
 */
class PhotoTextScheduleSyncRequest extends FormRequest
{
    /**
     * Разрешает любой запрос.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Всегда ожидает JSON-ответ.
     */
    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * Правила: активный ресторан, опциональный scope категорий, окно 7 дней, entries с датами внутри окна.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $restaurantId = (int) $this->input('restaurant_id');

        $categoryBelongsToRestaurant = static function () use ($restaurantId) {
            return Rule::exists('max_menu_categories', 'id')
                ->where(static function ($query) use ($restaurantId): void {
                    $query->whereNull('deleted_at');

                    if ($restaurantId > 0) {
                        $query->where('restaurant_id', $restaurantId);
                    }
                });
        };

        return [
            'restaurant_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('max_restaurants', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'category_id' => [
                'nullable',
                'integer',
                'min:1',
                $categoryBelongsToRestaurant(),
            ],
            'category_ids' => ['nullable', 'array', 'min:1'],
            'category_ids.*' => [
                'integer',
                'min:1',
                'distinct',
                $categoryBelongsToRestaurant(),
            ],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.name' => ['required', 'string', 'max:255'],
            'entries.*.dates' => ['required', 'array', 'min:1'],
            'entries.*.dates.*' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Доп. проверки: date_to = date_from + 6; даты entries внутри диапазона;
     * category_id и category_ids не конфликтуют.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hasCategoryId = $this->filled('category_id');
            $hasCategoryIds = is_array($this->input('category_ids')) && $this->input('category_ids') !== [];

            if ($hasCategoryId && $hasCategoryIds) {
                $validator->errors()->add(
                    'category_ids',
                    'Передайте либо category_id, либо category_ids, но не оба сразу.',
                );

                return;
            }

            $dateFrom = (string) $this->input('date_from');
            $dateTo = (string) $this->input('date_to');
            $from = CarbonImmutable::createFromFormat('Y-m-d', $dateFrom);

            if ($from === false) {
                $validator->errors()->add('date_from', 'Некорректная дата начала графика.');

                return;
            }

            $expectedTo = $from->startOfDay()->addDays(6)->toDateString();

            if ($dateTo !== $expectedTo) {
                $validator->errors()->add(
                    'date_to',
                    'Диапазон графика должен быть ровно 7 дней (date_to = date_from + 6 дней).',
                );

                return;
            }

            /** @var list<array{name?: mixed, dates?: mixed}> $entries */
            $entries = $this->input('entries', []);

            foreach ($entries as $index => $entry) {
                $dates = $entry['dates'] ?? [];

                if (! is_array($dates)) {
                    continue;
                }

                foreach ($dates as $dateIndex => $date) {
                    if (! is_string($date)) {
                        continue;
                    }

                    if ($date < $dateFrom || $date > $dateTo) {
                        $validator->errors()->add(
                            "entries.{$index}.dates.{$dateIndex}",
                            'Дата должна входить в диапазон date_from…date_to.',
                        );
                    }
                }
            }
        });
    }

    /**
     * Сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.exists' => 'Ресторан не найден или неактивен.',
            'category_id.exists' => 'Категория меню не найдена для выбранного ресторана.',
            'category_ids.min' => 'Укажите хотя бы одну категорию.',
            'category_ids.*.exists' => 'Категория меню не найдена для выбранного ресторана.',
            'category_ids.*.distinct' => 'Список категорий не должен содержать дубликаты.',
            'date_from.required' => 'Укажите дату начала графика.',
            'date_to.required' => 'Укажите дату окончания графика.',
            'entries.required' => 'Передайте хотя бы одну позицию графика.',
            'entries.min' => 'Передайте хотя бы одну позицию графика.',
        ];
    }

    /**
     * Идентификатор активного ресторана.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }

    /**
     * Scope категорий для матча и apply: null — весь каталог ресторана.
     * Источник: category_ids, иначе одиночный category_id.
     *
     * @return list<int>|null
     */
    public function categoryIds(): ?array
    {
        /** @var list<int>|null $ids */
        $ids = $this->validated('category_ids');

        if (is_array($ids) && $ids !== []) {
            $normalized = array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                $ids,
            )));
            sort($normalized);

            return $normalized;
        }

        $single = $this->validated('category_id');

        if ($single === null) {
            return null;
        }

        return [(int) $single];
    }

    /**
     * Начало окна графика (Y-m-d).
     */
    public function dateFrom(): string
    {
        return (string) $this->validated('date_from');
    }

    /**
     * Конец окна графика (Y-m-d), ровно date_from + 6.
     */
    public function dateTo(): string
    {
        return (string) $this->validated('date_to');
    }

    /**
     * Канонические позиции графика.
     *
     * @return list<PhotoTextScheduleEntryDto>
     */
    public function entries(): array
    {
        /** @var list<array{name: string, dates: list<string>}> $entries */
        $entries = $this->validated('entries');

        return array_values(array_map(
            static function (array $entry): PhotoTextScheduleEntryDto {
                /** @var list<string> $dates */
                $dates = array_values(array_unique($entry['dates']));
                sort($dates);

                return new PhotoTextScheduleEntryDto(
                    name: trim((string) $entry['name']),
                    dates: $dates,
                );
            },
            $entries,
        ));
    }
}
