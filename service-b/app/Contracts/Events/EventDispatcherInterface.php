<?php

namespace App\Contracts\Events;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
