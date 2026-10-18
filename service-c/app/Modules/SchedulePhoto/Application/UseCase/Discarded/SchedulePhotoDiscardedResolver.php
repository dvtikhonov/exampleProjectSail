<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Discarded;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoDiscardedResolverInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunAccessInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoResolveDiscardedDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoResolveResultDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleEntryDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoScopeDishLookup;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoStoredMatchEntries;
use DateTimeImmutable;
use Throwable;

/**
 * Human-resolve discarded: канон в scope, окно дат, повторный match.
 *
 * Владение прогоном и CAS-запись — через SchedulePhotoRunAccessInterface,
 * те же правила, что у apply и admin-чтения.
 */
final class SchedulePhotoDiscardedResolver implements SchedulePhotoDiscardedResolverInterface
{
    public function __construct(
        private readonly SchedulePhotoRunAccessInterface $access,
        private readonly SchedulePhotoAliasDictionaryInterface $aliasDictionary,
        private readonly SchedulePhotoAliasesWriterInterface $aliasesWriter,
        private readonly SchedulePhotoCatalogScopeBuilderInterface $catalogScope,
        private readonly SchedulePhotoPhotoTextClientInterface $photoText,
        private readonly SchedulePhotoStoredMatchEntries $storedMatchEntries,
        private readonly SchedulePhotoScopeDishLookup $scopeDishLookup,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolveDiscarded(SchedulePhotoResolveDiscardedDto $dto, int $actorMaxUserId): SchedulePhotoResolveResultDto
    {
        $run = $this->access->findOwnedOrFail($dto->runId, $actorMaxUserId);

        if ($run->status !== SchedulePhotoRunStatus::Matched) {
            throw new SchedulePhotoDomainException(
                'Разрешить отброшенную строку можно только при status=matched.',
                409,
            );
        }

        $rawTitle = trim($dto->rawTitle);
        if ($rawTitle === '') {
            throw new SchedulePhotoDomainException('Укажите raw_title отброшенной строки.', 422);
        }

        // OCR-complete запрещает дубли raw_title; для legacy — первая совпавшая строка.
        $discardedIndex = null;
        foreach ($run->discarded as $index => $row) {
            if (trim($row->rawTitle) === $rawTitle) {
                $discardedIndex = $index;
                break;
            }
        }

        if ($discardedIndex === null) {
            throw new SchedulePhotoDomainException('Строка не найдена среди отброшенных.', 422);
        }

        $dishName = $dto->dishName !== null ? trim($dto->dishName) : '';
        if ($dishName === '') {
            $fromAlias = $this->aliasDictionary->resolveCanon($rawTitle);
            $dishName = $fromAlias !== null ? trim($fromAlias) : '';
        }

        if ($dishName === '') {
            throw new SchedulePhotoDomainException(
                'Нет канона: укажите dish_name или добавьте алиас в словарь.',
                422,
            );
        }

        $dates = $this->datesForResolvedDiscarded(
            $run->dateFrom,
            $run->dateTo,
            $run->discarded[$discardedIndex],
            $dto->dates,
        );
        $scope = $this->catalogScope->build($run->baseUrl, $run->restaurantId, $run->categoryIds);
        $canonInScope = $this->scopeDishLookup->findScopeDishName($scope, $dishName);
        if ($canonInScope === null) {
            throw new SchedulePhotoDomainException(
                'Блюдо «'.$dishName.'» нет в выбранных категориях каталога.',
                422,
            );
        }

        $entries = $run->matchResult !== null
            ? $this->storedMatchEntries->fromMatchResult($run->matchResult)
            : [];
        $entries[] = new SchedulePhotoScheduleEntryDto(name: $canonInScope, dates: $dates);

        $result = $this->photoText->match(
            $run->baseUrl,
            $run->restaurantId,
            $run->categoryIds,
            $run->dateFrom,
            $run->dateTo,
            $entries,
        );

        $remainingDiscarded = [];
        foreach ($run->discarded as $index => $row) {
            if ($index === $discardedIndex) {
                continue;
            }
            $remainingDiscarded[] = $row;
        }

        $updated = $this->access->updateOrConflict(
            $dto->runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Matched,
                matchResult: $result,
                discarded: $remainingDiscarded,
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::Matched],
            'Разрешить отброшенную строку можно только при status=matched.',
        );

        // Ручной выбор из селектора → словарь только после успешного update.
        // Сбой match или конфликт статуса файл не меняют.
        // Сбой upsert после CAS — soft-fail: строка уже resolved, warning в результате.
        $aliasPersistError = null;
        if ($dto->dishName !== null && trim($dto->dishName) !== '') {
            try {
                $this->aliasesWriter->upsertPair($rawTitle, $canonInScope);
            } catch (Throwable $e) {
                $aliasPersistError = 'Строка разрешена, но не удалось сохранить пару в phototext-dish-aliases.md: '
                    .$e->getMessage();
            }
        }

        return new SchedulePhotoResolveResultDto(
            run: $updated,
            aliasPersistError: $aliasPersistError,
        );
    }

    /**
     * Даты resolve: дни с фото на discarded, если OCR их сохранил.
     * Иначе — даты из запроса (ручной выбор дня). Все дни окна сами не подставляются.
     *
     * @param  list<string>  $requestedDates
     * @return list<string>
     */
    private function datesForResolvedDiscarded(
        string $dateFrom,
        string $dateTo,
        SchedulePhotoDiscardedRowDto $discardedRow,
        array $requestedDates,
    ): array {
        $photoDates = $this->storedMatchEntries->normalizeDates($discardedRow->dates);
        if ($photoDates === []) {
            $requested = $this->assertDatesInWindow($dateFrom, $dateTo, $requestedDates);
            if ($this->isEntireWindow($dateFrom, $dateTo, $requested)) {
                throw new SchedulePhotoDomainException(
                    'У строки нет даты с фото. Укажите день заголовка группы, не все дни окна.',
                    422,
                );
            }

            return $requested;
        }

        $insideWindow = [];
        foreach ($photoDates as $date) {
            if ($date >= $dateFrom && $date <= $dateTo) {
                $insideWindow[] = $date;
            }
        }

        if ($insideWindow === []) {
            throw new SchedulePhotoDomainException(
                'Дата с фото вне окна '.$dateFrom.'…'.$dateTo.'.',
                422,
            );
        }

        return array_values(array_unique($insideWindow));
    }

    /**
     * @param  list<string>  $dates
     */
    private function isEntireWindow(string $dateFrom, string $dateTo, array $dates): bool
    {
        $expected = [];
        $cursor = new DateTimeImmutable($dateFrom);
        $end = new DateTimeImmutable($dateTo);

        while ($cursor <= $end) {
            $expected[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        $actual = $dates;
        sort($actual);

        return $actual === $expected;
    }

    /**
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function assertDatesInWindow(string $dateFrom, string $dateTo, array $dates): array
    {
        if ($dates === []) {
            throw new SchedulePhotoDomainException('Укажите хотя бы одну дату в окне графика.', 422);
        }

        $normalized = [];
        foreach ($dates as $date) {
            $trimmed = trim($date);
            if ($trimmed === '') {
                continue;
            }
            if (
                DateTimeImmutable::createFromFormat('!Y-m-d', $trimmed) === false
                || $trimmed < $dateFrom
                || $trimmed > $dateTo
            ) {
                throw new SchedulePhotoDomainException(
                    'Дата '.$trimmed.' вне окна '.$dateFrom.'…'.$dateTo.'.',
                    422,
                );
            }
            $normalized[] = $trimmed;
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            throw new SchedulePhotoDomainException('Укажите хотя бы одну дату в окне графика.', 422);
        }

        return $normalized;
    }
}
