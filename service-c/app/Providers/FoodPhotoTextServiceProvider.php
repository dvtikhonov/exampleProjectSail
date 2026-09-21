<?php

namespace App\Providers;

use App\Contracts\Food\PhotoText\PhotoTextComboRefGrouperInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishLineResolverInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishNameMatcherInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextScheduleApplierInterface;
use App\Contracts\Food\PhotoText\PhotoTextScheduleMatcherInterface;
use App\Contracts\Food\PhotoText\PhotoTextSchedulePlacementServiceInterface;
use App\Services\Food\PhotoText\PhotoTextComboRefGrouper;
use App\Services\Food\PhotoText\PhotoTextDishLineResolver;
use App\Services\Food\PhotoText\PhotoTextDishNameMatcher;
use App\Services\Food\PhotoText\PhotoTextManualOrderPlacementService;
use App\Services\Food\PhotoText\PhotoTextScheduleApplier;
use App\Services\Food\PhotoText\PhotoTextScheduleMatcher;
use App\Services\Food\PhotoText\PhotoTextSchedulePlacementService;
use Illuminate\Support\ServiceProvider;

/**
 * DI-привязки Food PhotoText.
 */
class FoodPhotoTextServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и сервисы PhotoText.
     */
    public function register(): void
    {
        $this->app->bind(PhotoTextComboRefGrouperInterface::class, PhotoTextComboRefGrouper::class);
        $this->app->bind(PhotoTextDishNameMatcherInterface::class, PhotoTextDishNameMatcher::class);
        $this->app->bind(PhotoTextDishLineResolverInterface::class, PhotoTextDishLineResolver::class);
        $this->app->bind(PhotoTextManualOrderPlacementServiceInterface::class, PhotoTextManualOrderPlacementService::class);
        $this->app->bind(PhotoTextScheduleMatcherInterface::class, PhotoTextScheduleMatcher::class);
        $this->app->bind(PhotoTextScheduleApplierInterface::class, PhotoTextScheduleApplier::class);
        $this->app->bind(PhotoTextSchedulePlacementServiceInterface::class, PhotoTextSchedulePlacementService::class);
    }
}
