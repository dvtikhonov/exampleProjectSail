<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishAdminRepositoryInterface;
use App\Contracts\Food\DishAdminServiceInterface;
use App\Contracts\Food\DishImageUploadInterface;
use App\Contracts\Food\DishImageUrlResolverInterface;
use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\DTO\Food\AdminDishDto;
use App\DTO\Food\CreateDishDto;
use App\DTO\Food\ImportDishRowDto;
use App\DTO\Food\UpdateDishDto;
use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Dish;
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
    ) {}

    /**
     * Возвращает список блюд для админки.
     *
     * @return list<AdminDishDto>
     */
    public function list(?int $restaurantId = null, ?int $categoryId = null, ?string $nameSearch = null): array
    {
        $paginator = $this->dishRepository->paginateForAdmin($restaurantId, $categoryId, $nameSearch);

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

        return DB::transaction(function () use ($dto, $photo): AdminDishDto {
            $dish = $this->dishRepository->create($this->attributesFromCreateDto($dto));
            $imagePath = $this->dishImageUpload->upload($dish->id, $photo);
            $dish = $this->dishRepository->update($dish, ['image_url' => $imagePath]);

            return $this->mapToAdminDto($dish);
        });
    }

    /**
     * Импорт строки из таблицы: при точном совпадении названия обновляет только цену.
     *
     * @throws FoodDomainException
     */
    public function importSpreadsheetRow(ImportDishRowDto $row, int $menuCategoryId): void
    {
        $this->assertMenuCategoryExists($menuCategoryId);

        $existing = $this->dishRepository->findByNameAndMenuCategoryId($row->name, $menuCategoryId);

        if ($existing !== null) {
            $this->dishRepository->update($existing, ['price' => $row->price]);

            return;
        }

        $this->createWithDefaultImage(new CreateDishDto(
            name: $row->name,
            menuCategoryId: $menuCategoryId,
            description: $row->description,
            weight: $row->weight,
            weightUnit: $row->weightUnit,
            price: $row->price,
            vatRate: $row->vatRate,
            isAvailable: $row->isAvailable,
        ));
    }

    /**
     * Создание блюда с placeholder-изображением (импорт из таблицы).
     *
     * @throws FoodDomainException
     */
    public function createWithDefaultImage(CreateDishDto $dto): AdminDishDto
    {
        $this->assertMenuCategoryExists($dto->menuCategoryId);

        return DB::transaction(function () use ($dto): AdminDishDto {
            $dish = $this->dishRepository->create($this->attributesFromCreateDto($dto));
            $imagePath = $this->defaultImageProvider->copyForDish($dish->id);
            $dish = $this->dishRepository->update($dish, ['image_url' => $imagePath]);

            return $this->mapToAdminDto($dish);
        });
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

        return DB::transaction(function () use ($dish, $dto, $photo): AdminDishDto {
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
