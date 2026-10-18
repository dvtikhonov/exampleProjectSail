<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent;

use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-модель прогона «фото графика» (таблица schedule_photo_runs).
 *
 * Миграция таблицы — отдельным шагом после согласия на migrate.
 */
class SchedulePhotoRun extends Model
{
    protected $table = 'schedule_photo_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'status',
        'base_url',
        'date_from',
        'date_to',
        'restaurant_id',
        'category_ids',
        'created_by_max_user_id',
        'photo_path',
        'match_result',
        'discarded',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SchedulePhotoRunStatus::class,
            'date_from' => 'date:Y-m-d',
            'date_to' => 'date:Y-m-d',
            'restaurant_id' => 'integer',
            'created_by_max_user_id' => 'integer',
            'category_ids' => 'array',
            'match_result' => 'array',
            'discarded' => 'array',
        ];
    }
}
