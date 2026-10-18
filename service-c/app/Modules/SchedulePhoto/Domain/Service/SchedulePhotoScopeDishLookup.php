<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Domain\Service;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;

/**
 * Поиск канонического имени блюда в catalog scope (та же нормализация ключа, что у словаря).
 */
final class SchedulePhotoScopeDishLookup
{
    public function __construct(
        private readonly SchedulePhotoAliasDictionaryInterface $aliasDictionary,
    ) {}

    /**
     * Имя блюда из scope с нормализацией ключа словаря алиасов.
     *
     * @param  list<SchedulePhotoCatalogScopeDishDto>  $scope
     */
    public function findScopeDishName(array $scope, string $dishName): ?string
    {
        $needle = $this->aliasDictionary->normalizeKey($dishName);
        if ($needle === '') {
            return null;
        }

        foreach ($scope as $dish) {
            if ($this->aliasDictionary->normalizeKey($dish->name) === $needle) {
                return $dish->name;
            }
        }

        return null;
    }
}
