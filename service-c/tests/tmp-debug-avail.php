<?php

declare(strict_types=1);

use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;
use App\Models\Food\MenuCategory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$today = CarbonImmutable::now('Europe/Moscow')->toDateString();
$categoryIds = MenuCategory::query()->where('restaurant_id', 2)->pluck('id');

$payload = [
    'today_msk' => $today,
    'app_timezone' => config('app.timezone'),
    'category_ids' => $categoryIds->all(),
    'dishes_total' => Dish::query()->whereIn('menu_category_id', $categoryIds)->count(),
    'dishes_available' => Dish::query()->whereIn('menu_category_id', $categoryIds)->where('is_available', true)->count(),
    'avail_dates_today' => DishAvailabilityDate::query()->whereDate('date', $today)->count(),
    'avail_dates_r2_today' => DishAvailabilityDate::query()
        ->whereDate('date', $today)
        ->whereIn('dish_id', Dish::query()->whereIn('menu_category_id', $categoryIds)->select('id'))
        ->count(),
    'avail_dates_near' => DishAvailabilityDate::query()
        ->whereBetween('date', [
            CarbonImmutable::now('Europe/Moscow')->subDays(2)->toDateString(),
            CarbonImmutable::now('Europe/Moscow')->addDays(3)->toDateString(),
        ])
        ->selectRaw('date, count(*) as c')
        ->groupBy('date')
        ->orderBy('date')
        ->get()
        ->toArray(),
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
