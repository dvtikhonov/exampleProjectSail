<?php

namespace App\Providers;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletTableMetaProviderInterface;
use App\Models\SalesOutlet as SalesOutletModel;
use App\Repositories\SalesOutlets\EloquentSalesOutletRepository;
use App\Repositories\SalesOutlets\SalesOutletsMetadataRepository;
use App\Services\Auth\EloquentGatewayUserResolver;
use App\Services\Auth\LaravelGatewayAuthSession;
use App\Services\Auth\RequestGatewayUserContext;
use App\Services\SalesOutlets\ConfigSalesOutletTableMetaProvider;
use App\Services\SalesOutlets\SalesOutletService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GatewayUserContextInterface::class, RequestGatewayUserContext::class);
        $this->app->bind(GatewayUserResolverInterface::class, EloquentGatewayUserResolver::class);
        $this->app->bind(GatewayAuthSessionInterface::class, LaravelGatewayAuthSession::class);
        $this->app->bind(SalesOutletsMetadataRepositoryInterface::class, SalesOutletsMetadataRepository::class);
        $this->app->bind(SalesOutletTableMetaProviderInterface::class, ConfigSalesOutletTableMetaProvider::class);
        $this->app->singleton(SalesOutletQueryFilter::class);
        $this->app->bind(SalesOutletServiceInterface::class, SalesOutletService::class);
        $this->app->bind(SalesOutletRepositoryInterface::class, EloquentSalesOutletRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('salesOutlet', function (string $value) {
            $outlet = app(SalesOutletRepositoryInterface::class)->findById((int) $value);

            return $outlet ?? throw (new ModelNotFoundException())->setModel(SalesOutletModel::class, [$value]);
        });
    }
}
