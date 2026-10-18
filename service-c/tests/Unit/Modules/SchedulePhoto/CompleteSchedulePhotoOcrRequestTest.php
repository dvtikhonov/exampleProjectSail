<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Interface\Http\Requests\CompleteSchedulePhotoOcrRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Unit: лимиты размера payload OCR-колбэка (entries/discarded/dates)
 * и уникальность discarded.*.raw_title.
 */
final class CompleteSchedulePhotoOcrRequestTest extends TestCase
{
    public function test_rejects_entries_above_max_200(): void
    {
        $entries = [];
        for ($i = 0; $i < 201; $i++) {
            $entries[] = [
                'raw_title' => 'dish-'.$i,
                'dates' => ['2026-08-13'],
            ];
        }

        $validator = $this->validate([
            'run_id' => '550e8400-e29b-41d4-a716-446655440000',
            'entries' => $entries,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('entries'));
    }

    public function test_rejects_discarded_above_max_200(): void
    {
        $discarded = [];
        for ($i = 0; $i < 201; $i++) {
            $discarded[] = [
                'raw_title' => 'raw-'.$i,
                'reason' => 'dish_not_found',
            ];
        }

        $validator = $this->validate([
            'run_id' => '550e8400-e29b-41d4-a716-446655440000',
            'error' => 'ocr failed',
            'discarded' => $discarded,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('discarded'));
    }

    public function test_rejects_entry_dates_above_max_14(): void
    {
        $dates = [];
        for ($i = 1; $i <= 15; $i++) {
            $dates[] = sprintf('2026-08-%02d', $i);
        }

        $validator = $this->validate([
            'run_id' => '550e8400-e29b-41d4-a716-446655440000',
            'entries' => [
                ['raw_title' => 'Пюре', 'dates' => $dates],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('entries.0.dates'));
    }

    public function test_rejects_duplicate_discarded_raw_titles(): void
    {
        $validator = $this->validate([
            'run_id' => '550e8400-e29b-41d4-a716-446655440000',
            'entries' => [],
            'discarded' => [
                ['raw_title' => 'пюрэшка', 'reason' => 'dish_not_found', 'dates' => ['2026-08-13']],
                ['raw_title' => '  пюрэшка  ', 'reason' => 'low_confidence', 'dates' => ['2026-08-14']],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('discarded.1.raw_title'));
    }

    public function test_accepts_distinct_discarded_raw_titles(): void
    {
        $validator = $this->validate([
            'run_id' => '550e8400-e29b-41d4-a716-446655440000',
            'entries' => [],
            'discarded' => [
                ['raw_title' => 'пюрэшка', 'reason' => 'dish_not_found'],
                ['raw_title' => 'каша', 'reason' => 'dish_not_found'],
            ],
        ]);

        $this->assertTrue($validator->passes(), (string) $validator->errors());
    }

    public function test_accepts_boundary_limits(): void
    {
        $dates = [];
        for ($i = 1; $i <= 14; $i++) {
            $dates[] = sprintf('2026-08-%02d', $i);
        }

        $entries = [];
        for ($i = 0; $i < 200; $i++) {
            $entries[] = [
                'raw_title' => 'dish-'.$i,
                'dates' => $dates,
            ];
        }

        $discarded = [];
        for ($i = 0; $i < 200; $i++) {
            $discarded[] = [
                'raw_title' => 'raw-'.$i,
                'reason' => 'dish_not_found',
                'dates' => $dates,
            ];
        }

        $validator = $this->validate([
            'run_id' => '550e8400-e29b-41d4-a716-446655440000',
            'entries' => $entries,
            'discarded' => $discarded,
        ]);

        $this->assertTrue($validator->passes(), (string) $validator->errors());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data): ValidatorContract
    {
        $request = CompleteSchedulePhotoOcrRequest::create('/', 'POST', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        return $validator;
    }
}
