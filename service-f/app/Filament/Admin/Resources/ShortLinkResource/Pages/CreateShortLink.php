<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShortLinkResource\Pages;

use App\Contracts\UrlShortener\OriginalUrlReachabilityCheckerInterface;
use App\Contracts\UrlShortener\UrlShortenerServiceInterface;
use App\DTO\UrlShortener\CreateShortLinkDto;
use App\Filament\Admin\Resources\ShortLinkResource;
use App\Filament\Admin\Resources\ShortLinkResource\Concerns\ConfirmsBrokenOriginalUrlBeforeSave;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Страница создания короткой ссылки через UrlShortenerService (код генерируется автоматически). */
class CreateShortLink extends CreateRecord
{
    use ConfirmsBrokenOriginalUrlBeforeSave;

    protected static string $resource = ShortLinkResource::class;

    private UrlShortenerServiceInterface $urlShortenerService;

    private OriginalUrlReachabilityCheckerInterface $originalUrlReachabilityChecker;

    public function boot(
        UrlShortenerServiceInterface $urlShortenerService,
        OriginalUrlReachabilityCheckerInterface $originalUrlReachabilityChecker,
    ): void {
        $this->urlShortenerService = $urlShortenerService;
        $this->originalUrlReachabilityChecker = $originalUrlReachabilityChecker;
    }

    protected function getOriginalUrlReachabilityChecker(): OriginalUrlReachabilityCheckerInterface
    {
        return $this->originalUrlReachabilityChecker;
    }

    protected function persistShortLinkAfterBrokenUrlConfirmation(): void
    {
        $this->create();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureOriginalUrlIsReachableOrConfirm($data['original_url']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->resetBrokenUrlConfirmationFlag();
    }

    /** Делегирует создание сервисному слою вместо прямого Eloquent::create. */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->urlShortenerService->createShortLink(
            new CreateShortLinkDto(
                userId: (int) auth()->id(),
                originalUrl: $data['original_url'],
            ),
        );
    }
}
