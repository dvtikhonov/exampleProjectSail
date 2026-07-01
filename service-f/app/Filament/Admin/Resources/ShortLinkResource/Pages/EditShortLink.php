<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShortLinkResource\Pages;

use App\Contracts\UrlShortener\OriginalUrlReachabilityCheckerInterface;
use App\Contracts\UrlShortener\UrlShortenerServiceInterface;
use App\Filament\Admin\Resources\ShortLinkResource;
use App\Filament\Admin\Resources\ShortLinkResource\Concerns\ConfirmsBrokenOriginalUrlBeforeSave;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** Редактирование и удаление собственной короткой ссылки. */
class EditShortLink extends EditRecord
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Удалить'),
        ];
    }

    protected function persistShortLinkAfterBrokenUrlConfirmation(): void
    {
        $this->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['original_url'] === $this->getRecord()->original_url) {
            return $data;
        }

        $this->ensureOriginalUrlIsReachableOrConfirm($data['original_url']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->resetBrokenUrlConfirmationFlag();
    }

    /** Удаление через UrlShortenerService (проверка владельца в репозитории). */
    protected function handleRecordDeletion(Model $record): void
    {
        $this->urlShortenerService->deleteShortLink(
            shortLinkId: (int) $record->getKey(),
            userId: (int) auth()->id(),
        );
    }
}
