<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Domain\Enum;

/**
 * Статус асинхронного прогона «фото графика» (OCR → match → confirm → apply).
 *
 * applying — захват между matched и HTTP PhotoText apply: второй confirm
 * этот статус не перезаписывает и PhotoText повторно не вызывает.
 */
enum SchedulePhotoRunStatus: string
{
    case Pending = 'pending';
    case OcrRunning = 'ocr_running';
    case Matched = 'matched';
    case Applying = 'applying';
    case Failed = 'failed';
    case Applied = 'applied';
}
