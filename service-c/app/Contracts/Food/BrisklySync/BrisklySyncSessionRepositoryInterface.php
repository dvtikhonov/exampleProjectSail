<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklySyncSessionRecord;
use App\Enums\Food\BrisklySync\BrisklySyncSessionStatus;

/**
 * Persistence сессий Briskly sync (без Eloquent в сервисах).
 */
interface BrisklySyncSessionRepositoryInterface
{
    /**
     * Создаёт сессию и возвращает запись.
     *
     * @param  array{
     *     restaurant_id: int,
     *     created_by_max_user_id: int|null,
     *     vps_category_id: int|null,
     *     search_text: string|null,
     *     clarification: string|null,
     *     status: BrisklySyncSessionStatus
     * }  $attributes
     */
    public function create(array $attributes): BrisklySyncSessionRecord;

    /**
     * Находит сессию по UUID.
     */
    public function findById(string $sessionId): ?BrisklySyncSessionRecord;

    /**
     * Обновляет поля сессии и возвращает актуальную запись.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $sessionId, array $attributes): BrisklySyncSessionRecord;
}
