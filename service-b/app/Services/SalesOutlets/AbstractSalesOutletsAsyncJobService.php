<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Enums\AsyncJobStatus;

abstract class AbstractSalesOutletsAsyncJobService
{
    abstract protected function jobRepository(): SalesOutletsAsyncJobRepositoryInterface;

    abstract protected function defaultFailureMessage(): string;

    protected function markJobAsFailed(string $uuid, ?string $errorMessage = null): void
    {
        $job = $this->jobRepository()->findByUuid($uuid);

        if ($job === null || $job->status === AsyncJobStatus::Failed) {
            return;
        }

        $this->jobRepository()->updateStatus(
            $job,
            AsyncJobStatus::Failed,
            errorMessage: $errorMessage ?? $this->defaultFailureMessage(),
        );
    }
}
