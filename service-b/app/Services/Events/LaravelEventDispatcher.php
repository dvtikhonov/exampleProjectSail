<?php

namespace App\Services\Events;

use App\Contracts\Events\EventDispatcherInterface;

class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}
