<?php

namespace App\Providers;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\Contracts\Queue\JobDispatcherInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\HtmlTableRendererInterface;
use App\Contracts\SalesOutlets\MailReportConfigProviderInterface;
use App\Contracts\SalesOutlets\ReportFileStorageInterface;
use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\Contracts\SalesOutlets\ReportProcessingDelayConfigInterface;
use App\Contracts\SalesOutlets\ReportProcessingDelayInterface;
use App\Contracts\SalesOutlets\ReportStorageConfigInterface;
use App\Contracts\SalesOutlets\SalesOutletReportFilterDtoFactoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsJobQueueInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportApiServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportContextFactoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadCapabilityInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadPresentationInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureHandlerInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportJobProcessorInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessorWorkerInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStatsBroadcasterInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStatsServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Repositories\SalesOutlets\EloquentSalesOutletsDataRepository;
use App\Repositories\SalesOutlets\EloquentSalesOutletsReportJobRepository;
use App\Repositories\SalesOutlets\EloquentSalesOutletsReportStatsService;
use App\Repositories\SalesOutlets\SalesOutletsMetadataRepository;
use App\Services\Auth\EloquentGatewayUserResolver;
use App\Services\Auth\LaravelGatewayAuthSession;
use App\Services\Queue\LaravelJobDispatcher;
use App\Services\SalesOutlets\LaravelReportMailSender;
use App\Services\SalesOutlets\LaravelSalesOutletsJobQueue;
use App\Services\SalesOutlets\LocalReportFileStorage;
use App\Services\SalesOutlets\Reports\ConfigReportProcessingDelay;
use App\Services\SalesOutlets\Reports\ConfigSalesOutletsReportsConfig;
use App\Services\SalesOutlets\Reports\Html\ConfigMailReportConfigProvider;
use App\Services\SalesOutlets\Reports\Html\HtmlTableRenderer;
use App\Services\SalesOutlets\Reports\SalesOutletsReportContextFactory;
use App\Services\SalesOutlets\Reports\SalesOutletsReportStrategyRegistry;
use App\Services\SalesOutlets\Reports\Strategies\CsvDownloadReportStrategy;
use App\Services\SalesOutlets\Reports\Strategies\HtmlEmailReportStrategy;
use App\Services\SalesOutlets\SalesOutletReportFilterDtoFactory;
use App\Services\SalesOutlets\SalesOutletsReportApiService;
use App\Services\SalesOutlets\SalesOutletsReportDownloadService;
use App\Services\SalesOutlets\SalesOutletsReportJobFailureHandler;
use App\Services\SalesOutlets\SalesOutletsReportJobProcessor;
use App\Services\SalesOutlets\SalesOutletsReportStatsBroadcaster;
use App\Services\SalesOutlets\SalesOutletsReportWorkerService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriter;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriterInterface;
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ConfigSalesOutletsReportsConfig::class, function ($app): ConfigSalesOutletsReportsConfig {
            return new ConfigSalesOutletsReportsConfig(
                config: $app->make(Repository::class),
                environment: $app->environment(),
            );
        });
        $this->app->alias(ConfigSalesOutletsReportsConfig::class, ReportStorageConfigInterface::class);
        $this->app->alias(ConfigSalesOutletsReportsConfig::class, ReportProcessingDelayConfigInterface::class);

        $this->app->bind(SalesOutletsAsyncJobRepositoryInterface::class, EloquentSalesOutletsReportJobRepository::class);
        $this->app->bind(SalesOutletsReportStatsServiceInterface::class, EloquentSalesOutletsReportStatsService::class);
        $this->app->bind(SalesOutletsReportStatsBroadcasterInterface::class, SalesOutletsReportStatsBroadcaster::class);
        $this->app->bind(SalesOutletsReportApiServiceInterface::class, SalesOutletsReportApiService::class);
        $this->app->bind(SalesOutletsReportDownloadServiceInterface::class, SalesOutletsReportDownloadService::class);
        $this->app->bind(SalesOutletsReportProcessorWorkerInterface::class, SalesOutletsReportWorkerService::class);
        $this->app->bind(SalesOutletsReportJobFailureServiceInterface::class, SalesOutletsReportWorkerService::class);
        $this->app->bind(SalesOutletsDataRepositoryInterface::class, EloquentSalesOutletsDataRepository::class);
        $this->app->bind(SalesOutletsMetadataRepositoryInterface::class, SalesOutletsMetadataRepository::class);
        $this->app->bind(SalesOutletReportFilterDtoFactoryInterface::class, SalesOutletReportFilterDtoFactory::class);
        $this->app->bind(ReportFileStorageInterface::class, function ($app): LocalReportFileStorage {
            $disk = $app->make(ReportStorageConfigInterface::class)->storageDisk();

            return new LocalReportFileStorage(
                filesystem: $app->make('filesystem')->disk($disk),
            );
        });
        $this->app->bind(ReportMailSenderInterface::class, LaravelReportMailSender::class);
        $this->app->bind(ReportProcessingDelayInterface::class, ConfigReportProcessingDelay::class);
        $this->app->bind(MailReportConfigProviderInterface::class, ConfigMailReportConfigProvider::class);
        $this->app->bind(HtmlTableRendererInterface::class, HtmlTableRenderer::class);
        $this->app->bind(SalesOutletsReportContextFactoryInterface::class, SalesOutletsReportContextFactory::class);
        $this->app->bind(SalesOutletsReportJobProcessorInterface::class, SalesOutletsReportJobProcessor::class);
        $this->app->bind(SalesOutletsReportJobFailureHandlerInterface::class, SalesOutletsReportJobFailureHandler::class);
        $this->app->bind(SalesOutletsJobQueueInterface::class, LaravelSalesOutletsJobQueue::class);
        $this->app->bind(JobDispatcherInterface::class, LaravelJobDispatcher::class);
        $this->app->bind(GatewayUserResolverInterface::class, EloquentGatewayUserResolver::class);
        $this->app->bind(GatewayAuthSessionInterface::class, LaravelGatewayAuthSession::class);
        $this->app->bind(CsvReportWriterInterface::class, CsvReportWriter::class);
        $this->app->singleton(SalesOutletQueryFilter::class);

        $this->app->tag([
            CsvDownloadReportStrategy::class,
            HtmlEmailReportStrategy::class,
        ], 'sales-outlets.report-strategies');

        $this->app->singleton(SalesOutletsReportStrategyRegistry::class, function ($app): SalesOutletsReportStrategyRegistry {
            return new SalesOutletsReportStrategyRegistry(
                $app->tagged('sales-outlets.report-strategies'),
            );
        });

        // One singleton, segregated interfaces: resolver vs download capability vs presentation.
        $this->app->alias(
            SalesOutletsReportStrategyRegistry::class,
            SalesOutletsReportStrategyResolverInterface::class,
        );
        $this->app->alias(
            SalesOutletsReportStrategyRegistry::class,
            SalesOutletsReportDownloadCapabilityInterface::class,
        );
        $this->app->alias(
            SalesOutletsReportStrategyRegistry::class,
            SalesOutletsReportDownloadPresentationInterface::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
