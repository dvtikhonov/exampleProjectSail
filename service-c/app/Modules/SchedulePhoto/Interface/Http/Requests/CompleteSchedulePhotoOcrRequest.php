<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Interface\Http\Requests;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoOcrCallbackDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Валидация POST /api/food/internal/schedule-photo/ocr-complete (колбэк sidecar).
 *
 * XOR: entries | error; опционально discarded[].
 */
class CompleteSchedulePhotoOcrRequest extends FormRequest
{
    /**
     * Разрешает запрос (секрет — middleware schedule-photo.capture.secret).
     */
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
            'run_id' => ['required', 'uuid'],
            'entries' => ['sometimes', 'nullable', 'array', 'max:200'],
            'entries.*.raw_title' => ['required_with:entries', 'string', 'max:500'],
            'entries.*.dates' => ['required_with:entries', 'array', 'min:1', 'max:14'],
            'entries.*.dates.*' => ['required', 'date_format:Y-m-d'],
            'error' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discarded' => ['sometimes', 'array', 'max:200'],
            'discarded.*.raw_title' => ['required_with:discarded', 'string', 'max:500'],
            'discarded.*.reason' => ['required_with:discarded', 'string', 'max:500'],
            'discarded.*.dates' => ['sometimes', 'array', 'max:14'],
            'discarded.*.dates.*' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * entries XOR error (пустой entries[] допустим — matched_count=0);
     * discarded.*.raw_title уникальны после trim (resolve ищет по заголовку).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasEntries = $this->exists('entries');
            $hasError = $this->filled('error');

            if ($hasEntries === $hasError) {
                $validator->errors()->add(
                    'entries',
                    'Нужно передать либо entries, либо error (XOR).',
                );
            }

            $this->rejectDuplicateDiscardedRawTitles($validator);
        });
    }

    /**
     * Запрет дублей discarded.*.raw_title: иначе resolve берёт только первую строку.
     */
    private function rejectDuplicateDiscardedRawTitles(Validator $validator): void
    {
        $discarded = $this->input('discarded');
        if (! is_array($discarded)) {
            return;
        }

        /** @var array<string, int> $seenIndexByRaw */
        $seenIndexByRaw = [];
        foreach ($discarded as $index => $row) {
            if (! is_array($row) || ! array_key_exists('raw_title', $row)) {
                continue;
            }

            $rawTitle = trim((string) $row['raw_title']);
            if ($rawTitle === '') {
                continue;
            }

            if (isset($seenIndexByRaw[$rawTitle])) {
                $validator->errors()->add(
                    'discarded.'.$index.'.raw_title',
                    'Значения discarded.*.raw_title должны быть уникальны.',
                );

                continue;
            }

            $seenIndexByRaw[$rawTitle] = (int) $index;
        }
    }

    /**
     * Domain DTO колбэка.
     */
    public function toCallbackDto(): SchedulePhotoOcrCallbackDto
    {
        $validated = $this->validated();

        /** @var list<array{raw_title: string, dates: list<string>}>|null $entries */
        $entries = null;
        if (isset($validated['entries']) && is_array($validated['entries'])) {
            $entries = [];
            foreach ($validated['entries'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                /** @var list<string> $dates */
                $dates = array_values(array_map(
                    static fn (mixed $d): string => (string) $d,
                    is_array($row['dates'] ?? null) ? $row['dates'] : [],
                ));
                $entries[] = [
                    'raw_title' => (string) ($row['raw_title'] ?? ''),
                    'dates' => $dates,
                ];
            }
        }

        /** @var list<SchedulePhotoDiscardedRowDto> $discarded */
        $discarded = [];
        if (isset($validated['discarded']) && is_array($validated['discarded'])) {
            foreach ($validated['discarded'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                /** @var list<string> $dates */
                $dates = array_values(array_unique(array_map(
                    static fn (mixed $d): string => (string) $d,
                    is_array($row['dates'] ?? null) ? $row['dates'] : [],
                )));
                $discarded[] = new SchedulePhotoDiscardedRowDto(
                    rawTitle: (string) ($row['raw_title'] ?? ''),
                    reason: (string) ($row['reason'] ?? ''),
                    dates: $dates,
                );
            }
        }

        return new SchedulePhotoOcrCallbackDto(
            runId: (string) $validated['run_id'],
            entries: $entries,
            error: isset($validated['error']) ? (string) $validated['error'] : null,
            discarded: $discarded,
        );
    }
}
