<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Providers;

use App\Contracts\Shared\FileStorageInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoAdminRunInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoAdminViewBuilderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryCacheInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoDiscardedResolverInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoIdGeneratorInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoLocalAiAccessInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoOcrCallbackInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoOcrJobInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrOrchestratorInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextProbeInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunAccessInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunApplyInterface;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoSidecarHealthProbeInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoUiOriginConfigBuilderInterface;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Filesystem\FilesystemSchedulePhotoAliasesReader;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Filesystem\FilesystemSchedulePhotoAliasesWriter;
use App\Modules\SchedulePhoto\Infrastructure\FoodMax\FoodMaxSchedulePhotoLocalAiAccess;
use App\Modules\SchedulePhoto\Infrastructure\Http\HttpSchedulePhotoOcrOrchestrator;
use App\Modules\SchedulePhoto\Infrastructure\Http\HttpSchedulePhotoPhotoTextClient;
use App\Modules\SchedulePhoto\Infrastructure\Http\HttpSchedulePhotoPhotoTextProbe;
use App\Modules\SchedulePhoto\Infrastructure\Http\HttpSchedulePhotoSidecarHealthProbe;
use App\Modules\SchedulePhoto\Infrastructure\Laravel\LaravelSchedulePhotoOcrQueue;
use App\Modules\SchedulePhoto\Infrastructure\Laravel\LaravelSchedulePhotoPhotoStore;
use App\Modules\SchedulePhoto\Infrastructure\Dictionary\MarkdownSchedulePhotoAliasDictionary;
use App\Modules\SchedulePhoto\Infrastructure\Laravel\RandomBytesSchedulePhotoIdGenerator;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\EloquentSchedulePhotoRunRepository;
use App\Modules\SchedulePhoto\Application\UseCase\Admin\SchedulePhotoAdminViewBuilder;
use App\Modules\SchedulePhoto\Application\UseCase\Catalog\SchedulePhotoCatalogScopeBuilder;
use App\Modules\SchedulePhoto\Application\UseCase\Discarded\SchedulePhotoDiscardedResolver;
use App\Modules\SchedulePhoto\Application\UseCase\Ocr\SchedulePhotoOcrCompleter;
use App\Modules\SchedulePhoto\Application\UseCase\Ocr\SchedulePhotoOcrJobHandler;
use App\Modules\SchedulePhoto\Application\UseCase\Readiness\SchedulePhotoReadinessChecker;
use App\Modules\SchedulePhoto\Application\UseCase\Start\SchedulePhotoRunAccess;
use App\Modules\SchedulePhoto\Application\UseCase\Apply\SchedulePhotoRunApplier;
use App\Modules\SchedulePhoto\Application\UseCase\Start\SchedulePhotoRunStarter;
use App\Modules\SchedulePhoto\Application\UseCase\Admin\SchedulePhotoUiOriginConfigBuilder;
use App\Modules\SchedulePhoto\Domain\Support\SchedulePhotoOriginAllowList;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * DI-привязки и маршруты модуля SchedulePhoto (фото графика).
 */
class SchedulePhotoServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и реализации модуля.
     */
    public function register(): void
    {
        $this->app->bind(
            SchedulePhotoPhotoTextProbeInterface::class,
            function ($app): HttpSchedulePhotoPhotoTextProbe {
                return new HttpSchedulePhotoPhotoTextProbe(
                    $app->make(HttpClientInterface::class),
                    (string) config('phototext.agent_token', ''),
                    (int) config('schedule_photo.phototext_probe_timeout_seconds', 10),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoPhotoTextClientInterface::class,
            function ($app): HttpSchedulePhotoPhotoTextClient {
                return new HttpSchedulePhotoPhotoTextClient(
                    $app->make(HttpClientInterface::class),
                    new SchedulePhotoOriginAllowList($this->allowedBaseUrls()),
                    (string) config('phototext.agent_token', ''),
                    (string) config('phototext.write_token', ''),
                    (int) config('schedule_photo.phototext_probe_timeout_seconds', 10),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoSidecarHealthProbeInterface::class,
            function ($app): HttpSchedulePhotoSidecarHealthProbe {
                return new HttpSchedulePhotoSidecarHealthProbe(
                    $app->make(HttpClientInterface::class),
                    (string) config('schedule_photo.orchestrator_base_url', ''),
                    (int) config('schedule_photo.sidecar_health_timeout_seconds', 5),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoOcrOrchestratorInterface::class,
            function ($app): HttpSchedulePhotoOcrOrchestrator {
                return new HttpSchedulePhotoOcrOrchestrator(
                    $app->make(HttpClientInterface::class),
                    (string) config('schedule_photo.orchestrator_base_url', ''),
                    (int) config('schedule_photo.orchestrator_handshake_timeout_seconds', 60),
                    (string) config('schedule_photo.capture_secret', ''),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoOcrQueueInterface::class,
            function ($app): LaravelSchedulePhotoOcrQueue {
                return new LaravelSchedulePhotoOcrQueue(
                    $app->make(JobDispatcherInterface::class),
                    (int) config('schedule_photo.orchestrator_handshake_timeout_seconds', 60),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoAliasesReaderInterface::class,
            function (): FilesystemSchedulePhotoAliasesReader {
                return new FilesystemSchedulePhotoAliasesReader($this->aliasCandidatePaths());
            },
        );

        $this->app->singleton(MarkdownSchedulePhotoAliasDictionary::class);

        $this->app->singleton(
            SchedulePhotoAliasDictionaryInterface::class,
            function ($app): MarkdownSchedulePhotoAliasDictionary {
                return $app->make(MarkdownSchedulePhotoAliasDictionary::class);
            },
        );

        $this->app->singleton(
            SchedulePhotoAliasDictionaryCacheInterface::class,
            function ($app): MarkdownSchedulePhotoAliasDictionary {
                return $app->make(MarkdownSchedulePhotoAliasDictionary::class);
            },
        );

        $this->app->bind(
            SchedulePhotoAliasesWriterInterface::class,
            function ($app): FilesystemSchedulePhotoAliasesWriter {
                return new FilesystemSchedulePhotoAliasesWriter(
                    $this->aliasCandidatePaths(),
                    $app->make(SchedulePhotoAliasDictionaryInterface::class),
                    $app->make(SchedulePhotoAliasDictionaryCacheInterface::class),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoPhotoStoreInterface::class,
            function ($app): LaravelSchedulePhotoPhotoStore {
                return new LaravelSchedulePhotoPhotoStore(
                    $app->make(FileStorageInterface::class),
                    (string) config('schedule_photo.photo_disk', 'local'),
                    (string) config('schedule_photo.photo_directory', 'schedule-photo'),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoIdGeneratorInterface::class,
            RandomBytesSchedulePhotoIdGenerator::class,
        );

        $this->app->bind(
            SchedulePhotoCatalogScopeBuilderInterface::class,
            SchedulePhotoCatalogScopeBuilder::class,
        );

        $this->app->bind(
            SchedulePhotoRunRepositoryInterface::class,
            EloquentSchedulePhotoRunRepository::class,
        );

        $this->app->bind(
            SchedulePhotoRunAccessInterface::class,
            SchedulePhotoRunAccess::class,
        );

        $this->app->bind(
            SchedulePhotoDiscardedResolverInterface::class,
            SchedulePhotoDiscardedResolver::class,
        );

        $this->app->bind(
            SchedulePhotoAdminViewBuilderInterface::class,
            SchedulePhotoAdminViewBuilder::class,
        );

        $this->app->bind(
            SchedulePhotoUiOriginConfigBuilderInterface::class,
            function (): SchedulePhotoUiOriginConfigBuilder {
                return new SchedulePhotoUiOriginConfigBuilder(
                    (string) config('schedule_photo.local_base_url', ''),
                    (string) config('schedule_photo.default_base_url', ''),
                    $this->allowedBaseUrls(),
                );
            },
        );

        $this->app->bind(
            SchedulePhotoLocalAiAccessInterface::class,
            FoodMaxSchedulePhotoLocalAiAccess::class,
        );

        $this->app->bind(
            SchedulePhotoReadinessCheckerInterface::class,
            SchedulePhotoReadinessChecker::class,
        );

        $this->app->bind(
            SchedulePhotoAdminRunInterface::class,
            SchedulePhotoRunStarter::class,
        );

        $this->app->bind(
            SchedulePhotoRunApplyInterface::class,
            SchedulePhotoRunApplier::class,
        );

        $this->app->bind(
            SchedulePhotoOcrCallbackInterface::class,
            SchedulePhotoOcrCompleter::class,
        );

        $this->app->bind(
            SchedulePhotoOcrJobInterface::class,
            SchedulePhotoOcrJobHandler::class,
        );
    }

    /**
     * Подключает маршруты модуля под prefix /api (как routes/api.php).
     */
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }

    /**
     * Allow-list origin PhotoText из конфига (пустые и нестроковые значения отбрасываются).
     *
     * @return list<string>
     */
    private function allowedBaseUrls(): array
    {
        /** @var list<string>|mixed $allowedRaw */
        $allowedRaw = config('schedule_photo.allowed_base_urls', []);
        if (! is_array($allowedRaw)) {
            return [];
        }

        $allowed = [];
        foreach ($allowedRaw as $origin) {
            if (is_string($origin) && trim($origin) !== '') {
                $allowed[] = $origin;
            }
        }

        return $allowed;
    }

    /**
     * Кандидаты пути к phototext-dish-aliases.md.
     * Источник истины: .cursor/commands; fallback — копия в service-c.
     *
     * @return list<string>
     */
    private function aliasCandidatePaths(): array
    {
        $explicit = trim((string) config('schedule_photo.aliases_path', ''));

        return array_values(array_filter([
            $explicit,
            dirname(base_path()).DIRECTORY_SEPARATOR.'.cursor'
                .DIRECTORY_SEPARATOR.'commands'
                .DIRECTORY_SEPARATOR.'phototext-dish-aliases.md',
            base_path('phototext-dish-aliases.md'),
        ], static fn (string $path): bool => $path !== ''));
    }
}
