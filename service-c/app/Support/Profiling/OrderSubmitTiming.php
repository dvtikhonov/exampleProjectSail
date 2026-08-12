<?php

declare(strict_types=1);

namespace App\Support\Profiling;

/**
 * Временные метрики пути POST /orders/submit (профиль latency / очередь runtime).
 */
final class OrderSubmitTiming
{
    public const REQUEST_ATTRIBUTE = 'order_submit_timing';

    /**
     * Собирает значение заголовка Server-Timing из измерений сервиса.
     *
     * @param  array{t_tx_ms?: float|int, t_notify_ms?: float|int, t_submit_ms?: float|int}  $timing
     */
    public static function toServerTimingHeader(array $timing): string
    {
        return sprintf(
            't_tx;dur=%.1f, t_notify;dur=%.1f, t_submit;dur=%.1f',
            (float) ($timing['t_tx_ms'] ?? 0),
            (float) ($timing['t_notify_ms'] ?? 0),
            (float) ($timing['t_submit_ms'] ?? 0),
        );
    }
}
