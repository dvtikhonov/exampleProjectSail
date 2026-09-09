<?php

declare(strict_types=1);

namespace App\Http\Mappers;

use App\DTO\Max\MaxMiniAppAccessContextDto;
use App\Http\Support\MaxAppRequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Маппинг HTTP Request → контекст доступа MAX mini-app (без секретов).
 */
final class MaxMiniAppAccessContextMapper
{
    /**
     * Собирает контекст для логирования страницы mini-app.
     */
    public function fromPageRequest(Request $request): MaxMiniAppAccessContextDto
    {
        return $this->fromRequest($request);
    }

    /**
     * Собирает контекст для логирования auth (длина init_data без содержимого).
     */
    public function fromAuthRequest(Request $request): MaxMiniAppAccessContextDto
    {
        return $this->fromRequest(
            $request,
            strlen((string) $request->input('init_data', '')),
        );
    }

    /**
     * Базовый маппинг полей запроса в DTO.
     */
    private function fromRequest(Request $request, ?int $initDataLength = null): MaxMiniAppAccessContextDto
    {
        return new MaxMiniAppAccessContextDto(
            host: MaxAppRequestContext::requestHost($request),
            isTunnel: MaxAppRequestContext::isPublicTunnelRequest($request),
            ip: $request->ip(),
            userAgent: Str::limit((string) $request->userAgent(), 120, '…'),
            initDataLength: $initDataLength,
        );
    }
}
