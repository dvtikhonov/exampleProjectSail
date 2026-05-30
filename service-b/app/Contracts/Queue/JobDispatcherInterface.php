<?php

namespace App\Contracts\Queue;

interface JobDispatcherInterface
{
    public function dispatch(object $job): void;
}
