<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

/**
 * Результат одной проверки готовности (VPS PhotoText или sidecar).
 */
readonly class SchedulePhotoProbeResultDto
{
    public function __construct(
        public bool $ok,
        public string $message,
        /**
         * HTTP-статус ответа origin. 0 — ответа PhotoText не было
         * (запрос не выполнен или тело не JSON).
         */
        public int $status = 0,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
        ];
    }
}
