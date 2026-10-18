<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Interface\Jobs;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoOcrJobInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Handshake start sidecar OCR (как RunBrisklySyncMatchJob); LLM wait не в этом job.
 */
class RunSchedulePhotoOcrJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Без ретраев: повторный LLM-вызов дорогой. */
    public int $tries = 1;

    /** Таймаут одной попытки (секунды); задаётся в конструкторе. */
    public int $timeout;

    /** Unique-lock чуть дольше timeout. */
    public int $uniqueFor;

    /**
     * @param  string  $runId  UUID прогона
     * @param  int  $timeoutSeconds  лимит выполнения job
     */
    public function __construct(
        public readonly string $runId,
        int $timeoutSeconds,
    ) {
        $this->timeout = max(1, $timeoutSeconds);
        $this->uniqueFor = $this->timeout + 60;
    }

    /**
     * Ключ уникальности — один OCR на прогон.
     */
    public function uniqueId(): string
    {
        return $this->runId;
    }

    /**
     * Выполняет handshake OCR, если прогон ещё в ожидающем статусе.
     */
    public function handle(SchedulePhotoOcrJobInterface $runs): void
    {
        $runs->performQueuedOcr($this->runId);
    }

    /**
     * Timeout/падение воркера: failed только из pending или ocr_running.
     * Уже успешные matched, applying и applied не затираются.
     */
    public function failed(?Throwable $exception): void
    {
        app(SchedulePhotoOcrJobInterface::class)->failQueuedOcr($this->runId);
    }
}
