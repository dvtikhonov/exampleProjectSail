<?php

use App\Modules\FoodReport\Providers\FoodReportServiceProvider;
use App\Modules\MaxIncomingRelay\Providers\MaxIncomingRelayServiceProvider;
use App\Modules\SchedulePhoto\Providers\SchedulePhotoServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FoodServiceProvider;
use App\Providers\MaxServiceProvider;
use App\Providers\SharedInfrastructureProvider;

return [
    SharedInfrastructureProvider::class,
    FoodServiceProvider::class,
    FoodReportServiceProvider::class,
    MaxIncomingRelayServiceProvider::class,
    SchedulePhotoServiceProvider::class,
    MaxServiceProvider::class,
    AppServiceProvider::class,
];
