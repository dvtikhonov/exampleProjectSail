<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

/**
 * Входные данные старта прогона «фото графика» (после валидации Form Request).
 */
readonly class SchedulePhotoStartRunDto
{
    /**
     * @param  list<int>  $categoryIds
     * @param  string  $photoAbsolutePath  абсолютный путь к временно сохранённому фото
     */
    public function __construct(
        public string $baseUrl,
        public string $dateFrom,
        public int $restaurantId,
        public array $categoryIds,
        public string $photoAbsolutePath,
        public int $createdByMaxUserId,
    ) {}
}
