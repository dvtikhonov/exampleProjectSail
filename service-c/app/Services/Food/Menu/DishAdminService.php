<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAdminRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminServiceInterface;
use App\Contracts\Food\Menu\DishImageUploadInterface;
use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\DTO\Food\Menu\AdminDishDto;
use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\ImportDishRowDto;
use App\DTO\Food\Menu\UpdateDishDto;
use App\Enums\Food\Menu\AdminDishAvailabilityFilter;
use App\Enums\Food\Menu\DishVatRate;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Dish;
use App\Services\Food\Shared\FoodMoneyFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Административный CRUD блюд меню.
 */
class DishAdminService implements DishAdminServiceInterface
{
    public function __construct(
        private readonly DishAdminRepositoryInterface $dishRepository,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
        private readonly DishImageUploadInterface $dishImageUpload,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishDefaultImageProvider $defaultImageProvider,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
    ) {}

    /**
     * Возвращает список блюд для админки.
     *
     * @return list<AdminDishDto>
     */
    public function list(
        ?int $restaurantId = null,
        ?int $categoryId = null,
        ?string $nameSearch = null,
        AdminDishAvailabilityFilter $availability = AdminDishAvailabilityFilter::All,
    ): array {
        $paginator = $this->dishRepository->paginateForAdmin(
            $restaurantId,
            $categoryId,
            $nameSearch,
            $availability->toIsAvailable(),
        );

        return array_map(
            fn (Dish $dish): AdminDishDto => $this->mapToAdminDto($dish),
            $paginator->items(),
        );
    }

    /**
     * Возвращает блюдо по идентификатору для админки.
     *
     * @throws FoodDomainException
     */
    public function show(int $dishId): AdminDishDto
    {
        $dish = $this->findDishOrFail($dishId);

        return $this->mapToAdminDto($dish);
    }

    /**
     * Создаёт блюдо.
     *
     * @throws FoodDomainException
     */
    public function create(CreateDishDto $dto, UploadedFile $photo): AdminDishDto
    {
        $this->assertMenuCategoryExists($dto->menuCategoryId);

        $result = DB::transaction(function () use ($dto, $photo): AdminDishDto {
            $dish = $this->dishRepository->create($this->attributesFromCreateDto($dto));
            $imagePath = $this->dishImageUpload->upload($dish->id, $photo);
            $dish = $this->dishRepository->update($dish, ['image_url' => $imagePath]);

            return $this->mapToAdminDto($dish);
        });

        $this->catalogCacheInvalidator->invalidateAll();

        return $result;
    }

    /**
     * Пакетный импорт строк из таблицы: при точном совпадении названия обновляет только цену.
     *
     * @param  list<ImportDishRowDto>  $rows
     *
     * @throws FoodDomainException
     */
    public function importSpreadsheetRows(array $rows, int $menuCategoryId): int
    {
        $this->assertMenuCategoryExists($menuCategoryId);

        if ($rows === []) {
            return 0;
        }

        $importedCount = DB::transaction(function () use ($rows, $menuCategoryId): int {
            /** @var array<string, ImportDishRowDto> $byName */
            $byName = [];
            foreach ($rows as $row) {
                $byName[$row->name] = $row;
            }

            $names = array_keys($byName);
            $existing = $this->dishRepository->findByNamesAndMenuCategoryId($names, $menuCategoryId);

            /** @var array<int, string> $pricesById */
            $pricesById = [];
            /** @var list<array<string, mixed>> $toCreate */
            $toCreate = [];

            foreach ($byName as $name => $row) {
                $dish = $existing->get($name);

                if ($dish !== null) {
                    $pricesById[(int) $dish->id] = $row->price;
                } else {
                    $toCreate[] = [
                        'menu_category_id' => $menuCategoryId,
                        'name' => $row->name,
                        'description' => $row->description,
                        'weight' => $row->weight,
                        'weight_unit' => $row->weightUnit->value,
                        'price' => $row->price,
                        'vat_rate' => $row->vatRate->value(),
                        'is_available' => $row->isAvailable,
                        'image_url' => null,
                    ];
                }
            }

            $this->dishRepository->updatePricesByIds($pricesById);

            $created = $this->dishRepository->createMany($toCreate);
            $imageUrls = $this->defaultImageProvider->copyForDishes(
                $created->map(static fn (Dish $dish): int => (int) $dish->id)->all(),
            );
            $this->dishRepository->updateImageUrlsByIds($imageUrls);

            return count($rows);
        });

        $this->catalogCacheInvalidator->invalidateAll();

        return $importedCount;
    }

    /**
     * Обновляет блюдо.
     *
     * @throws FoodDomainException
     */
    public function update(int $dishId, UpdateDishDto $dto, ?UploadedFile $photo = null): AdminDishDto
    {
        $dish = $this->findDishOrFail($dishId);
        $this->assertMenuCategoryExists($dto->menuCategoryId);

        $result = DB::transaction(function () use ($dish, $dto, $photo): AdminDishDto {
            $previousImagePath = $dish->image_url;
            $attributes = $this->attributesFromUpdateDto($dto);

            if ($photo !== null) {
                $attributes['image_url'] = $this->dishImageUpload->upload($dish->id, $photo);
            }

            $dish = $this->dishRepository->update($dish, $attributes);

            if ($photo !== null) {
                $this->dishImageUpload->deleteIfExists($previousImagePath);
            }

            return $this->mapToAdminDto($dish);
        });

        $this->catalogCacheInvalidator->invalidateAll();

        return $result;
    }

    /**
     * Удаляет блюдо.
     *
     * @throws FoodDomainException
     */
    public function delete(int $dishId): void
    {
        $dish = $this->findDishOrFail($dishId);

        if ($this->dishRepository->existsInDraftCarts($dishId)) {
            throw new FoodDomainException(
                'Нельзя удалить блюдо: оно есть в активных корзинах пользователей.',
                409,
            );
        }

        DB::transaction(function () use ($dish): void {
            $this->dishRepository->delete($dish);
        });

        $this->catalogCacheInvalidator->invalidateAll();
    }

    /**
     * Находит блюдо или выбрасывает доменное исключение.
     *
     * @throws FoodDomainException
     */
    private function findDishOrFail(int $dishId): Dish
    {
        $dish = $this->dishRepository->findById($dishId);

        if ($dish === null) {
            throw new FoodDomainException('Блюдо не найдено.', 404);
        }

        return $dish;
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

    /**
     * Собирает атрибуты модели из DTO создания блюда.
     *
     * @return array<string, mixed>
     */
    private function attributesFromCreateDto(CreateDishDto $dto): array
    {
        return [
            ...$this->baseAttributesFromDto($dto),
            'image_url' => null,
        ];
    }

    /**
     * Собирает атрибуты модели из DTO обновления блюда.
     *
     * @return array<string, mixed>
     */
    private function attributesFromUpdateDto(UpdateDishDto $dto): array
    {
        return $this->baseAttributesFromDto($dto);
    }

    /**
     * Собирает базовые атрибуты блюда из DTO.
     *
     * @return array<string, mixed>
     */
    private function baseAttributesFromDto(CreateDishDto|UpdateDishDto $dto): array
    {
        return [
            'menu_category_id' => $dto->menuCategoryId,
            'name' => $dto->name,
            'description' => $dto->description,
            'weight' => $dto->weight,
            'weight_unit' => $dto->weightUnit->value,
            'price' => $dto->price,
            'vat_rate' => $dto->vatRate->value(),
            'is_available' => $dto->isAvailable,
        ];
    }

    /**
     * Преобразует модель блюда в админский DTO.
     */
    private function mapToAdminDto(Dish $dish): AdminDishDto
    {
        $category = $dish->menuCategory;
        $restaurant = $category?->restaurant;
        $weightUnit = $dish->weight_unit ?? DishWeightUnit::Gram;
        $vatRate = DishVatRate::fromValue($dish->vat_rate);

        return new AdminDishDto(
            id: $dish->id,
            name: $dish->name,
            description: $dish->description,
            menuCategoryId: (int) $dish->menu_category_id,
            menuCategoryName: (string) $category?->name,
            restaurantId: (int) ($restaurant?->id ?? 0),
            restaurantName: (string) ($restaurant?->name ?? ''),
            weight: $this->formatWeight($dish->weight),
            weightUnit: $weightUnit->value,
            weightUnitLabel: $weightUnit->label(),
            price: $this->moneyFormatter->format($dish->price),
            vatRate: $vatRate->value(),
            vatRateLabel: $vatRate->label(),
            isAvailable: $dish->is_available,
            imageUrl: $this->imageUrlResolver->resolvePublicUrl($dish->id, $dish->image_url),
        );
    }

    /**
     * Форматирует вес блюда для ответа API.
     */
    private function formatWeight(mixed $weight): string
    {
        return (string) (int) round((float) $weight);
    }
}
