<?php

namespace App\Providers;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Queue\JobDispatcherInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsReportStatsRepositoryInterface;
use App\Contracts\SalesOutlets\HtmlTableRendererInterface;
use App\Contracts\SalesOutlets\MailReportConfigProviderInterface;
use App\Contracts\SalesOutlets\ReportCompletionPolicyInterface;
use App\Contracts\SalesOutlets\ReportFileStorageInterface;
use App\Contracts\SalesOutlets\ReportJobLifecycleInterface;
use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\Contracts\SalesOutlets\ReportProcessingDelayConfigInterface;
use App\Contracts\SalesOutlets\ReportProcessingDelayInterface;
use App\Contracts\SalesOutlets\ReportStorageConfigInterface;
use App\Contracts\SalesOutlets\ReportStrategyExecutionInterface;
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
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingOrchestratorInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessorWorkerInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStatsBroadcasterInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Events\SalesOutletReportJobMutated;
use App\Listeners\BroadcastReportJobStatsOnJobMutation;
use App\Listeners\LogSalesOutletReportJobMutation;
use App\Repositories\SalesOutlets\EloquentSalesOutletsDataRepository;
use App\Repositories\SalesOutlets\EloquentSalesOutletsReportJobRepository;
use App\Repositories\SalesOutlets\EloquentSalesOutletsReportStatsRepository;
use App\Repositories\SalesOutlets\SalesOutletsMetadataRepository;
use App\Services\Auth\EloquentGatewayUserResolver;
use App\Services\Auth\LaravelGatewayAuthSession;
use App\Services\Events\LaravelEventDispatcher;
use App\Services\Queue\LaravelJobDispatcher;
use App\Services\SalesOutlets\LaravelReportMailSender;
use App\Services\SalesOutlets\LaravelSalesOutletsJobQueue;
use App\Services\SalesOutlets\LocalReportFileStorage;
use App\Services\SalesOutlets\ReportCompletionPolicy;
use App\Services\SalesOutlets\ReportJobLifecycleService;
use App\Services\SalesOutlets\ReportStrategyExecutionService;
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
use App\Services\SalesOutlets\SalesOutletsReportProcessingOrchestrator;
use App\Services\SalesOutlets\SalesOutletsReportStatsBroadcaster;
use App\Services\SalesOutlets\SalesOutletsReportWorkerService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriter;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriterInterface;
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;

class AppServiceProvider extends ServiceProvider
{
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
        $this->app->bind(SalesOutletsReportStatsRepositoryInterface::class, EloquentSalesOutletsReportStatsRepository::class);
        $this->app->bind(SalesOutletsReportStatsBroadcasterInterface::class, SalesOutletsReportStatsBroadcaster::class);
        $this->app->bind(EventDispatcherInterface::class, LaravelEventDispatcher::class);
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

        $this->app->bind(ReportJobLifecycleInterface::class, ReportJobLifecycleService::class);
        $this->app->bind(ReportStrategyExecutionInterface::class, ReportStrategyExecutionService::class);
        $this->app->bind(ReportCompletionPolicyInterface::class, ReportCompletionPolicy::class);
        $this->app->bind(SalesOutletsReportProcessingOrchestratorInterface::class, SalesOutletsReportProcessingOrchestrator::class);
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

    public function boot(): void
    {
        Event::listen(
            SalesOutletReportJobMutated::class,
            BroadcastReportJobStatsOnJobMutation::class,
        );
        Event::listen(
            SalesOutletReportJobMutated::class,
            LogSalesOutletReportJobMutation::class,
        );
    }
}
