<?php

declare(strict_types=1);

namespace App\Repositories\Organization;

use App\Contracts\OrganizationRepositoryInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;

/**
 * Eloquent-реализация доступа к организациям Яндекс.Карт.
 *
 * Отвечает за поиск, upsert при подтверждении кандидата и обновление полей синхронизации.
 */
class EloquentOrganizationRepository implements OrganizationRepositoryInterface
{
    /** Возвращает организацию, привязанную к пользователю (не более одной на user_id). */
    public function findByUserId(int $userId): ?Organization
    {
        return Organization::query()
            ->where('user_id', $userId)
            ->first();
    }

    /** Ищет организацию по идентификатору в Яндекс.Картах. */
    public function findByYandexOrgId(string $yandexOrgId): ?Organization
    {
        return Organization::query()
            ->where('yandex_org_id', $yandexOrgId)
            ->first();
    }

    public function findById(int $organizationId): ?Organization
    {
        return Organization::query()->find($organizationId);
    }

    /**
     * Создаёт или обновляет организацию по yandex_org_id после подтверждения кандидата.
     *
     * При upsert сбрасывает статус синхронизации в Pending и очищает ошибки/время последнего sync.
     */
    public function upsertForUser(
        int $userId,
        string $sourceUrl,
        OrganizationCandidateDto $candidate,
    ): Organization {
        return Organization::query()->updateOrCreate(
            ['yandex_org_id' => $candidate->orgId],
            [
                'user_id' => $userId,
                'source_url' => $sourceUrl,
                'canonical_url' => $candidate->canonicalUrl,
                'name' => $candidate->name,
                'address' => $candidate->address,
                'average_rating' => $candidate->averageRating,
                'ratings_count' => $candidate->ratingsCount,
                'reviews_count' => $candidate->reviewsCount,
                'sync_status' => OrganizationSyncStatus::Pending,
                'sync_error' => null,
                'last_synced_at' => null,
            ],
        );
    }

    /** Обновляет статус синхронизации и опциональное сообщение об ошибке. */
    public function updateSyncStatus(
        int $organizationId,
        OrganizationSyncStatus $status,
        ?string $syncError = null,
    ): void {
        Organization::query()
            ->whereKey($organizationId)
            ->update([
                'sync_status' => $status->value,
                'sync_error' => $syncError,
            ]);
    }

    /**
     * Обновляет метаданные организации из результата парсинга.
     *
     * Адрес перезаписывается только если парсер вернул непустую строку.
     */
    public function updateFromParsedMeta(int $organizationId, ParsedOrganizationMetaDto $meta): void
    {
        $parsedAddress = trim($meta->address);

        $updates = [
            'canonical_url' => $meta->canonicalUrl,
            'yandex_org_id' => $meta->orgId,
            'name' => $meta->name,
            'average_rating' => $meta->averageRating,
            'ratings_count' => $meta->ratingsCount,
            'reviews_count' => $meta->reviewsCount,
        ];

        if ($parsedAddress !== '') {
            $updates['address'] = $parsedAddress;
        }

        Organization::query()
            ->whereKey($organizationId)
            ->update($updates);
    }

    /** Помечает синхронизацию успешной: статус Completed, без ошибки, с текущим last_synced_at. */
    public function markSyncCompleted(int $organizationId): void
    {
        Organization::query()
            ->whereKey($organizationId)
            ->update([
                'sync_status' => OrganizationSyncStatus::Completed->value,
                'sync_error' => null,
                'last_synced_at' => now(),
            ]);
    }
}
