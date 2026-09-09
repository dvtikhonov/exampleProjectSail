<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Полный репозиторий пользователей MAX mini-app.
 *
 * Composition ISP: объединяет identity / delivery / AI / manual-order / load-test порты.
 */
interface MaxUserRepositoryInterface extends MaxLoadTestUserRepositoryInterface, MaxUserAiAccessRepositoryInterface, MaxUserDeliveryRepositoryInterface, MaxUserIdentityRepositoryInterface, MaxUserManualOrderQueryRepositoryInterface {}
