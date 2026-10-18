<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Admin;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoAdminViewBuilderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoAdminRunViewDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedHintDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoScopeDishLookup;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoStoredMatchEntries;

/**
 * Admin view прогона: enriched discarded + catalog_scope для UI.
 *
 * Сверка канона при resolve discarded остаётся в резолвере. Здесь только подсказка
 * suggested_canon / suggested_in_scope.
 */
final class SchedulePhotoAdminViewBuilder implements SchedulePhotoAdminViewBuilderInterface
{
    public function __construct(
        private readonly SchedulePhotoCatalogScopeBuilderInterface $catalogScope,
        private readonly SchedulePhotoAliasDictionaryInterface $aliasDictionary,
        private readonly SchedulePhotoScopeDishLookup $scopeDishLookup,
        private readonly SchedulePhotoStoredMatchEntries $storedMatchEntries,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function build(SchedulePhotoRunRecord $run): SchedulePhotoAdminRunViewDto
    {
        try {
            $scope = $this->catalogScope->build($run->baseUrl, $run->restaurantId, $run->categoryIds);
        } catch (SchedulePhotoDomainException $exception) {
            return new SchedulePhotoAdminRunViewDto(
                run: $run,
                discarded: $this->enrichDiscarded($run->discarded, null),
                catalogScope: null,
                catalogError: $exception->getMessage(),
            );
        }

        return new SchedulePhotoAdminRunViewDto(
            run: $run,
            discarded: $this->enrichDiscarded($run->discarded, $scope),
            catalogScope: $scope,
            catalogError: null,
        );
    }

    /**
     * @param  list<SchedulePhotoDiscardedRowDto>  $discarded
     * @param  list<SchedulePhotoCatalogScopeDishDto>|null  $scope  null — каталог не собран, in-scope не утверждаем
     * @return list<SchedulePhotoDiscardedHintDto>
     */
    private function enrichDiscarded(array $discarded, ?array $scope): array
    {
        $enriched = [];

        foreach ($discarded as $row) {
            $rawTitle = trim($row->rawTitle);
            $reason = $row->reason;
            $suggested = $rawTitle !== ''
                ? $this->aliasDictionary->resolveCanon($rawTitle)
                : null;
            $inScope = false;
            if ($suggested !== null && $scope !== null) {
                $scopeName = $this->scopeDishLookup->findScopeDishName($scope, $suggested);
                if ($scopeName !== null) {
                    $suggested = $scopeName;
                    $inScope = true;
                }
            }

            $enriched[] = new SchedulePhotoDiscardedHintDto(
                rawTitle: $rawTitle,
                reason: $reason,
                dates: $this->storedMatchEntries->normalizeDates($row->dates),
                suggestedCanon: $suggested,
                suggestedInScope: $inScope,
                aliasMissing: $suggested === null,
            );
        }

        return $enriched;
    }
}
