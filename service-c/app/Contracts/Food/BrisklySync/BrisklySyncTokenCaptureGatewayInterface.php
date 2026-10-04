<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\Exceptions\Food\FoodDomainException;

/**
 * Порт серверного захвата Bearer Briskly через sidecar CDP (POST /capture-token).
 */
interface BrisklySyncTokenCaptureGatewayInterface
{
    /**
     * Захватывает JWT из уже открытого Chrome (CDP) через sidecar.
     *
     * @return non-empty-string нормализованный Bearer без префикса
     *
     * @throws FoodDomainException при недоступности capture / отсутствии токена (503/422)
     */
    public function captureToken(): string;
}
