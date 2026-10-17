<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Providers;

use App\Contracts\Shared\FileStorageInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoOcrOrchestratorInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextProbeInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunServiceInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoSidecarHealthProbeInterface;
use App\Modules\SchedulePhoto\Infrastructure\FilesystemSchedulePhotoAliasesReader;
use App\Modules\SchedulePhoto\Infrastructure\FilesystemSchedulePhotoAliasesWriter;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoOcrOrchestrator;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoPhotoTextClient;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoPhotoTextProbe;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoSidecarHealthProbe;
use App\Modules\SchedulePhoto\Infrastructure\LaravelSchedulePhotoOcrQueue;
use App\Modules\SchedulePhoto\Infrastructure\LaravelSchedulePhotoPhotoStore;
use App\Modules\SchedulePhoto\Infrastructure\MarkdownSchedulePhotoAliasDictionary;
use App\Modules\SchedulePhoto\Repositories\EloquentSchedulePhotoRunRepository;
use App\Modules\SchedulePhoto\Services\SchedulePhotoCatalogScopeBuilder;
use App\Modules\SchedulePhoto\Services\SchedulePhotoReadinessChecker;
use App\Modules\SchedulePhoto\Services\SchedulePhotoRunService;
use App\Modules\SchedulePhoto\Support\SchedulePhotoOriginAllowList;
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

        $this->app->singleton(
            SchedulePhotoAliasDictionaryInterface::class,
            MarkdownSchedulePhotoAliasDictionary::class,
        );

        $this->app->bind(
            SchedulePhotoAliasesWriterInterface::class,
            function ($app): FilesystemSchedulePhotoAliasesWriter {
                return new FilesystemSchedulePhotoAliasesWriter(
                    $this->aliasCandidatePaths(),
                    $app->make(SchedulePhotoAliasDictionaryInterface::class),
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
            SchedulePhotoCatalogScopeBuilderInterface::class,
            SchedulePhotoCatalogScopeBuilder::class,
        );

        $this->app->bind(
            SchedulePhotoRunRepositoryInterface::class,
            EloquentSchedulePhotoRunRepository::class,
        );

        $this->app->bind(
            SchedulePhotoReadinessCheckerInterface::class,
            SchedulePhotoReadinessChecker::class,
        );

        $this->app->bind(
            SchedulePhotoRunServiceInterface::class,
            SchedulePhotoRunService::class,
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
