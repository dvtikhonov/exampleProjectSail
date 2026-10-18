<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;

/**
 * Частичное обновление прогона: заданы только изменяемые поля (camelCase).
 *
 * null в свойстве ≠ «не трогать»: отсутствие поля отмечается через has*().
 * Фабрика {@see change()} отличает «не передано» (`false`) от явного `null`.
 */
readonly class SchedulePhotoRunPatchDto
{
    /**
     * @param  list<SchedulePhotoDiscardedRowDto>|null  $discarded
     * @param  array{
     *     status?: true,
     *     photoPath?: true,
     *     matchResult?: true,
     *     discarded?: true,
     *     errorMessage?: true
     * }  $set
     */
    public function __construct(
        public ?SchedulePhotoRunStatus $status = null,
        public ?string $photoPath = null,
        public ?SchedulePhotoScheduleResultDto $matchResult = null,
        public ?array $discarded = null,
        public ?string $errorMessage = null,
        private array $set = [],
    ) {}

    /**
     * @param  list<SchedulePhotoDiscardedRowDto>|false|null  $discarded
     */
    public static function change(
        SchedulePhotoRunStatus|false $status = false,
        string|false|null $photoPath = false,
        SchedulePhotoScheduleResultDto|false|null $matchResult = false,
        array|false|null $discarded = false,
        string|false|null $errorMessage = false,
    ): self {
        $set = [];
        $resolvedStatus = null;
        $resolvedPhotoPath = null;
        $resolvedMatchResult = null;
        $resolvedDiscarded = null;
        $resolvedErrorMessage = null;

        if ($status !== false) {
            $set['status'] = true;
            $resolvedStatus = $status;
        }
        if ($photoPath !== false) {
            $set['photoPath'] = true;
            $resolvedPhotoPath = $photoPath;
        }
        if ($matchResult !== false) {
            $set['matchResult'] = true;
            $resolvedMatchResult = $matchResult;
        }
        if ($discarded !== false) {
            $set['discarded'] = true;
            $resolvedDiscarded = $discarded;
        }
        if ($errorMessage !== false) {
            $set['errorMessage'] = true;
            $resolvedErrorMessage = $errorMessage;
        }

        return new self(
            status: $resolvedStatus,
            photoPath: $resolvedPhotoPath,
            matchResult: $resolvedMatchResult,
            discarded: $resolvedDiscarded,
            errorMessage: $resolvedErrorMessage,
            set: $set,
        );
    }

    public function hasStatus(): bool
    {
        return isset($this->set['status']);
    }

    public function hasPhotoPath(): bool
    {
        return isset($this->set['photoPath']);
    }

    public function hasMatchResult(): bool
    {
        return isset($this->set['matchResult']);
    }

    public function hasDiscarded(): bool
    {
        return isset($this->set['discarded']);
    }

    public function hasErrorMessage(): bool
    {
        return isset($this->set['errorMessage']);
    }

    public function isEmpty(): bool
    {
        return $this->set === [];
    }
}
