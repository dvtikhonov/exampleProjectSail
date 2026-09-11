<?php

use App\Modules\FoodReport\Providers\FoodReportServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FoodServiceProvider;
use App\Providers\MaxServiceProvider;
use App\Providers\SharedInfrastructureProvider;

return [
    SharedInfrastructureProvider::class,
    FoodServiceProvider::class,
    FoodReportServiceProvider::class,
    MaxServiceProvider::class,
    AppServiceProvider::class,
];
