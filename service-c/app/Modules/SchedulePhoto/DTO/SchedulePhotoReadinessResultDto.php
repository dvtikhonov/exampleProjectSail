<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

/**
 * Сводка готовности перед запуском LLM OCR (AI + VPS PhotoText + sidecar).
 */
readonly class SchedulePhotoReadinessResultDto
{
    public function __construct(
        public bool $ok,
        public bool $aiAccessOk,
        public string $aiAccessMessage,
        public bool $photoTextOk,
        public string $photoTextMessage,
        public bool $sidecarOk,
        public string $sidecarMessage,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     ai_access: array{ok: bool, message: string},
     *     phototext: array{ok: bool, message: string},
     *     sidecar: array{ok: bool, message: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'ai_access' => [
                'ok' => $this->aiAccessOk,
                'message' => $this->aiAccessMessage,
            ],
            'phototext' => [
                'ok' => $this->photoTextOk,
                'message' => $this->photoTextMessage,
            ],
            'sidecar' => [
                'ok' => $this->sidecarOk,
                'message' => $this->sidecarMessage,
            ],
        ];
    }
}
