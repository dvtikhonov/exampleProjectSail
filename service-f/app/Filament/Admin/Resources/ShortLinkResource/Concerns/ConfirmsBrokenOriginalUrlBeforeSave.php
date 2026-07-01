<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShortLinkResource\Concerns;

use App\Contracts\UrlShortener\OriginalUrlReachabilityCheckerInterface;
use Filament\Actions\Action;

/**
 * Проверка HTTP 200 для исходного URL перед сохранением короткой ссылки.
 *
 * @property bool $allowBrokenUrl
 */
trait ConfirmsBrokenOriginalUrlBeforeSave
{
    /** Разрешить сохранение после подтверждения недоступного URL. */
    public bool $allowBrokenUrl = false;

    abstract protected function getOriginalUrlReachabilityChecker(): OriginalUrlReachabilityCheckerInterface;

    /**
     * Модальное действие подтверждения (не отображается в header, монтируется программно).
     */
    public function confirmSaveBrokenUrlAction(): Action
    {
        return Action::make('confirmSaveBrokenUrl')
            ->label('Сохранить всё равно')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Ссылка недоступна')
            ->modalDescription(function (array $arguments): string {
                $statusCode = $arguments['statusCode'] ?? null;

                if ($statusCode === null) {
                    return 'Не удалось получить ответ HTTP 200 от указанного URL. Сохранить ссылку всё равно?';
                }

                return sprintf(
                    'Сервер вернул код %d вместо 200. Сохранить ссылку всё равно?',
                    $statusCode,
                );
            })
            ->modalSubmitActionLabel('Сохранить')
            ->action(function (): void {
                $this->allowBrokenUrl = true;
                $this->persistShortLinkAfterBrokenUrlConfirmation();
            });
    }

    /** Повторное сохранение после подтверждения недоступного URL (create/save). */
    abstract protected function persistShortLinkAfterBrokenUrlConfirmation(): void;

    /** Проверяет доступность URL; при ответе не 200 запрашивает подтверждение. */
    protected function ensureOriginalUrlIsReachableOrConfirm(string $originalUrl): void
    {
        if ($this->allowBrokenUrl) {
            return;
        }

        $result = $this->getOriginalUrlReachabilityChecker()->check($originalUrl);

        if ($result->isOk()) {
            return;
        }

        $this->mountAction('confirmSaveBrokenUrl', [
            'statusCode' => $result->httpStatusCode,
        ]);

        $this->halt();
    }

    protected function resetBrokenUrlConfirmationFlag(): void
    {
        $this->allowBrokenUrl = false;
    }
}
