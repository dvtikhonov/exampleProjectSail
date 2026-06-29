<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;
use App\Models\Dish;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class RestaurantSeeder extends Seeder
{
    private const SEED_ASSET_DIR = 'database/seeders/assets/dishes';

    private const SEED_STORAGE_PREFIX = 'dishes/seed';

    /**
     * @var list<string>
     */
    private array $seedImagePaths = [];

    public function run(): void
    {
        $this->publishSeedImages();

        $this->seedRestaurant(
            name: 'Пицца Макс',
            address: 'ул. Ленина, 10',
            categories: [
                'Пицца' => [
                    ['Маргарита', 450.00, 0],
                    ['Пепперони', 520.00, 1],
                    ['Четыре сыра', 580.00, 2],
                ],
                'Напитки' => [
                    ['Кола 0.5л', 120.00, 0],
                    ['Сок апельсиновый', 150.00, 1],
                ],
            ],
        );

        $this->seedRestaurant(
            name: 'Суши Бар',
            address: 'пр. Мира, 25',
            categories: [
                'Роллы' => [
                    ['Филадельфия', 390.00, 1],
                    ['Калифорния', 350.00, 2],
                    ['Дракон', 470.00, 0],
                ],
                'Супы' => [
                    ['Мисо', 180.00, 1],
                ],
            ],
        );

        $this->seedRestaurant(
            name: 'Бургер Хаус',
            address: 'ул. Гагарина, 7',
            categories: [
                'Бургеры' => [
                    ['Классический', 320.00, 2],
                    ['Чизбургер', 360.00, 0],
                    ['Двойной бекон', 490.00, 1],
                ],
                'Гарниры' => [
                    ['Картофель фри', 150.00, 2],
                    ['Луковые кольца', 170.00, 0],
                ],
            ],
        );
    }

    private function publishSeedImages(): void
    {
        $assetDir = base_path(self::SEED_ASSET_DIR);

        if (! is_dir($assetDir)) {
            throw new \RuntimeException('Seed dish assets directory is missing: '.self::SEED_ASSET_DIR);
        }

        $files = collect(File::files($assetDir))
            ->filter(static fn (\SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg'], true))
            ->sortBy(static fn (\SplFileInfo $file): string => $file->getFilename())
            ->values();

        if ($files->isEmpty()) {
            throw new \RuntimeException('No JPG seed assets found in '.self::SEED_ASSET_DIR);
        }

        $disk = Storage::disk('public');

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $storagePath = self::SEED_STORAGE_PREFIX.'/'.$filename;
            $disk->put($storagePath, File::get($file->getPathname()));
            $this->seedImagePaths[] = $storagePath;
        }
    }

    /**
     * @param  array<string, list<array{0: string, 1: float, 2?: int|null}>>  $categories
     */
    private function seedRestaurant(string $name, string $address, array $categories): void
    {
        $restaurant = Restaurant::query()->create([
            'name' => $name,
            'address' => $address,
            'is_active' => true,
        ]);

        $sortOrder = 1;

        foreach ($categories as $categoryName => $dishes) {
            $category = MenuCategory::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $categoryName,
                'sort_order' => $sortOrder,
            ]);

            foreach ($dishes as $dish) {
                $isDrinkCategory = in_array($categoryName, ['Напитки'], true);

                Dish::query()->create([
                    'menu_category_id' => $category->id,
                    'name' => $dish[0],
                    'description' => $this->resolveSeedDescription($dish[0]),
                    'weight' => $isDrinkCategory ? 500.000 : 350.000,
                    'weight_unit' => $isDrinkCategory ? DishWeightUnit::Milliliter : DishWeightUnit::Gram,
                    'image_url' => $this->resolveSeedImagePath($dish[2] ?? 0),
                    'price' => $dish[1],
                    'vat_rate' => DishVatRate::Ten->value(),
                    'is_available' => true,
                ]);
            }

            $sortOrder++;
        }
    }

    private function resolveSeedImagePath(int $index): string
    {
        if ($this->seedImagePaths === []) {
            throw new \RuntimeException('Seed dish images were not published.');
        }

        return $this->seedImagePaths[$index % count($this->seedImagePaths)];
    }

    private function resolveSeedDescription(string $dishName): string
    {
        return "Демо-описание блюда «{$dishName}».";
    }
}
