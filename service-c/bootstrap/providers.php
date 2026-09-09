<?php

use App\Providers\AppServiceProvider;
use App\Providers\FoodServiceProvider;
use App\Providers\MaxServiceProvider;
use App\Providers\SharedInfrastructureProvider;

return [
    SharedInfrastructureProvider::class,
    FoodServiceProvider::class,
    MaxServiceProvider::class,
    AppServiceProvider::class,
];
