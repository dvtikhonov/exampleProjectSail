<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Facade-composition портов управления MAX webhook (для обратной совместимости DI/тестов).
 *
 * Предпочтительная точка входа для команд — узкие интерфейсы:
 * {@see MaxWebhookSubscriptionClientInterface},
 * {@see MaxWebhookUrlProbeInterface},
 * {@see MaxWebhookStaleDevTunnelCleanerInterface}.
 */
interface MaxWebhookSubscriberInterface extends MaxWebhookStaleDevTunnelCleanerInterface, MaxWebhookSubscriptionClientInterface, MaxWebhookUrlProbeInterface {}
