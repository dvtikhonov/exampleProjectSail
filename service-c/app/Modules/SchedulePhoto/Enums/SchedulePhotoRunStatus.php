<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Enums;

/**
 * Статус асинхронного прогона «фото графика» (OCR → match → confirm → apply).
 */
enum SchedulePhotoRunStatus: string
{
    case Pending = 'pending';
    case OcrRunning = 'ocr_running';
    case Matched = 'matched';
    case Failed = 'failed';
    case Applied = 'applied';
}
