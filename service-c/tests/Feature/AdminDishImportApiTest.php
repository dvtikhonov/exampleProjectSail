<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\DishWeightUnit;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\Dish;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\DishSpreadsheetTestFileFactory;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminDishImportApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->resetFoodDomainTables();
    }

    public function test_import_creates_new_dishes_from_spreadsheet(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();
        $xlsxPath = DishSpreadsheetTestFileFactory::createXlsx([
            ['Борщ. 300г', '250'],
            ['Солянка. 350г', '320,50'],
        ]);

        try {
            $response = $this->postMultipart('/api/food/admin/dishes/import', [
                'file' => new UploadedFile($xlsxPath, 'menu.xlsx', null, null, true),
                'menu_category_id' => $fixture['category']->id,
            ], $auth['headers']);
        } finally {
            @unlink($xlsxPath);
        }

        $response
            ->assertOk()
            ->assertJsonPath('imported_count', 2);

        $this->assertDatabaseHas('max_dishes', [
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Борщ',
            'price' => '250.00',
            'weight' => '300.000',
            'weight_unit' => DishWeightUnit::Gram->value,
        ]);

        $this->assertDatabaseHas('max_dishes', [
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Солянка',
            'price' => '320.50',
            'weight' => '350.000',
        ]);
    }

    public function test_import_updates_only_price_when_dish_name_matches(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(
            dishName: 'Борщ',
            price: 199.00,
        );

        $existingDish = $fixture['dish'];
        $existingDish->update([
            'weight' => 300,
            'weight_unit' => DishWeightUnit::Gram,
            'description' => 'Классический борщ',
            'image_url' => 'dishes/'.$existingDish->id.'/keep-me.jpg',
        ]);

        Storage::disk('public')->put('dishes/'.$existingDish->id.'/keep-me.jpg', 'image');

        $auth = $this->menuManagerAuth();
        $xlsxPath = DishSpreadsheetTestFileFactory::createXlsx([
            ['Борщ. 500г', '299'],
        ]);

        try {
            $this->postMultipart('/api/food/admin/dishes/import', [
                'file' => new UploadedFile($xlsxPath, 'menu.xlsx', null, null, true),
                'menu_category_id' => $fixture['category']->id,
            ], $auth['headers'])
                ->assertOk()
                ->assertJsonPath('imported_count', 1);
        } finally {
            @unlink($xlsxPath);
        }

        $this->assertSame(1, Dish::query()->where('menu_category_id', $fixture['category']->id)->count());

        $updatedDish = Dish::query()->findOrFail($existingDish->id);

        $this->assertSame('299.00', (string) $updatedDish->price);
        $this->assertSame('300.000', (string) $updatedDish->weight);
        $this->assertSame('Классический борщ', $updatedDish->description);
        $this->assertSame('dishes/'.$existingDish->id.'/keep-me.jpg', $updatedDish->image_url);
        Storage::disk('public')->assertExists('dishes/'.$existingDish->id.'/keep-me.jpg');
    }

    public function test_import_creates_new_dish_when_name_differs_even_slightly(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(
            dishName: 'Борщ',
            price: 199.00,
        );

        $auth = $this->menuManagerAuth();
        $xlsxPath = DishSpreadsheetTestFileFactory::createXlsx([
            ['Борщ домашний. 300г', '250'],
        ]);

        try {
            $this->postMultipart('/api/food/admin/dishes/import', [
                'file' => new UploadedFile($xlsxPath, 'menu.xlsx', null, null, true),
                'menu_category_id' => $fixture['category']->id,
            ], $auth['headers'])
                ->assertOk()
                ->assertJsonPath('imported_count', 1);
        } finally {
            @unlink($xlsxPath);
        }

        $this->assertSame(2, Dish::query()->where('menu_category_id', $fixture['category']->id)->count());
        $this->assertDatabaseHas('max_dishes', [
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Борщ домашний',
            'price' => '250.00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function postMultipart(string $uri, array $data, array $headers): TestResponse
    {
        return $this->post($uri, $data, [
            ...$headers,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * @return array{user: MaxUser, token: string, headers: array<string, string>}
     */
    private function menuManagerAuth(): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_011,
                'first_name' => 'MenuImporter',
            ])),
            FoodOrderAdminRole::MenuManager,
        );
    }
}
