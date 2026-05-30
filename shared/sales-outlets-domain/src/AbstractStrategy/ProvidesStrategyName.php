<?php

namespace Shared\SalesOutletsDomain\AbstractStrategy;

trait ProvidesStrategyName
{
    public function getName(): string
    {
        return class_basename(static::class);
    }
}
