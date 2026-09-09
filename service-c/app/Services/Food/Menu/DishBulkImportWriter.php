<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAdminBulkRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminReadRepositoryInterface;
use App\Contracts\Food\Menu\DishBulkImportWriterInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\ImportDishRowDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Пакетная запись блюд из строк spreadsheet-импорта.
 */
class DishBulkImportWriter implements DishBulkImportWriterInterface
{
    public function __construct(
        private readonly DishAdminReadRepositoryInterface $dishReadRepository,
        private readonly DishAdminBulkRepositoryInterface $dishBulkRepository,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
        private readonly DishDefaultImageProvider $defaultImageProvider,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function importSpreadsheetRows(array $rows, int $menuCategoryId): int
    {
        $this->assertMenuCategoryExists($menuCategoryId);

        if ($rows === []) {
            return 0;
        }

        $importedCount = $this->transactionManager->run(function () use ($rows, $menuCategoryId): int {
            /** @var array<string, ImportDishRowDto> $byName */
            $byName = [];
            foreach ($rows as $row) {
                $byName[$row->name] = $row;
            }

            $names = array_keys($byName);
            $existing = $this->dishReadRepository->findByNamesAndMenuCategoryId($names, $menuCategoryId);

            /** @var array<int, string> $pricesById */
            $pricesById = [];
            /** @var list<CreateDishDto> $toCreate */
            $toCreate = [];

            foreach ($byName as $name => $row) {
                $dish = $existing[$name] ?? null;

                if ($dish !== null) {
                    $pricesById[$dish->id] = $row->price;
                } else {
                    $toCreate[] = new CreateDishDto(
                        name: $row->name,
                        menuCategoryId: $menuCategoryId,
                        description: $row->description,
                        weight: $row->weight,
                        weightUnit: $row->weightUnit,
                        price: $row->price,
                        vatRate: $row->vatRate,
                        isAvailable: $row->isAvailable,
                    );
                }
            }

            $this->dishBulkRepository->updatePricesByIds($pricesById);

            $created = $this->dishBulkRepository->createMany($toCreate);
            $imageUrls = $this->defaultImageProvider->copyForDishes(
                array_map(static fn (DishRecord $dish): int => $dish->id, $created),
            );
            $this->dishBulkRepository->updateImageUrlsByIds($imageUrls);

            return count($rows);
        });

        $this->catalogCacheInvalidator->invalidateAll();

        return $importedCount;
    }

    /**
     * Проверяет существование категории меню.
     *
     * @throws FoodDomainException
     */
    private function assertMenuCategoryExists(int $menuCategoryId): void
    {
        if ($this->menuCategoryRepository->findById($menuCategoryId) === null) {
            throw new FoodDomainException('Категория меню не найдена.', 422);
        }
    }
}
