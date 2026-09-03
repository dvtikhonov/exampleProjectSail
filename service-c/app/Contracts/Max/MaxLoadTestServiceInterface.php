<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\LoadTestCleanupResultDto;
use App\DTO\Max\LoadTestPrepareMenuResultDto;
use App\DTO\Max\LoadTestTokensResultDto;
use RuntimeException;

/**
 * Подготовка и очистка данных для k6-нагрузки mini-app (только local/testing).
 */
interface MaxLoadTestServiceInterface
{
    /**
     * Создаёт/находит load-test MaxUser и выдаёт Sanctum-токены scope max-miniapp.
     *
     * Путь к JSON задаёт delivery-слой (Artisan); сервис не резолвит storage_path().
     *
     * @throws RuntimeException если APP_ENV не local/testing или count некорректен
     */
    public function issueTokens(int $count, string $outputPath): LoadTestTokensResultDto;

    /**
     * Включает is_available у блюд активных ресторанов и инвалидирует кэш меню.
     *
     * Нужно перед k6, если cron food:sync-dish-availability обнулил меню
     * (нет offsets на weekday / устаревший график available_date).
     *
     * @throws RuntimeException если APP_ENV не local/testing
     */
    public function prepareMenu(): LoadTestPrepareMenuResultDto;

    /**
     * Удаляет заказы и корзины load-test пользователей (сначала orders, затем carts).
     *
     * @throws RuntimeException если APP_ENV не local/testing или count некорректен
     */
    public function cleanup(int $count): LoadTestCleanupResultDto;
}
