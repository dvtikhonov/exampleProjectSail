<?php

namespace App\Services\Queue;

use App\Contracts\Queue\JobDispatcherInterface;

class LaravelJobDispatcher implements JobDispatcherInterface
{
    public function dispatch(object $job): void
    {
        dispatch($job);
    }
}
