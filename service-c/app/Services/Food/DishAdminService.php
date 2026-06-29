<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUploadInterface;
use App\Contracts\Food\DishImageUrlResolverInterface;
use App\Contracts\Food\DishRepositoryInterface;
use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\DTO\Food\AdminDishDto;
use App\DTO\Food\CreateDishDto;
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
class DishAdminService
{
    public function __construct(
        private readonly DishRepositoryInterface $dishRepository,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
        private readonly DishImageUploadInterface $dishImageUpload,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
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
     * @throws FoodDomainException
     */
    public function show(int $dishId): AdminDishDto
    {
        $dish = $this->findDishOrFail($dishId);

        return $this->mapToAdminDto($dish);
    }

    /**
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
     * @throws FoodDomainException
     */
    private function assertMenuCategoryExists(int $menuCategoryId): void
    {
        if ($this->menuCategoryRepository->findById($menuCategoryId) === null) {
            throw new FoodDomainException('Категория меню не найдена.', 422);
        }
    }

    /**
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
     * @return array<string, mixed>
     */
    private function attributesFromUpdateDto(UpdateDishDto $dto): array
    {
        return $this->baseAttributesFromDto($dto);
    }

    /**
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

    private function formatWeight(mixed $weight): string
    {
        return (string) (int) round((float) $weight);
    }
}
