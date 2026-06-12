<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Dish;
use Illuminate\Database\Seeder;

/**
 * Одноразовое обновление image_url у уже засеянных блюд (без пересоздания ресторанов).
 * URL совпадают с RestaurantSeeder.
 */
class DishImageUrlBackfillSeeder extends Seeder
{
    /** @var array<string, string> */
    private const IMAGE_URLS_BY_NAME = [
        'Маргарита' => 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=200&h=200&fit=crop',
        'Пепперони' => 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=200&h=200&fit=crop',
        'Четыре сыра' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=200&h=200&fit=crop',
        'Кола 0.5л' => 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=200&h=200&fit=crop',
        'Сок апельсиновый' => 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=200&h=200&fit=crop',
        'Филадельфия' => 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?w=200&h=200&fit=crop',
        'Калифорния' => 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=200&h=200&fit=crop',
        'Дракон' => 'https://images.unsplash.com/photo-1611143669185-af224c5e3252?w=200&h=200&fit=crop',
        'Мисо' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=200&h=200&fit=crop',
        'Классический' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=200&h=200&fit=crop',
        'Чизбургер' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=200&h=200&fit=crop',
        'Двойной бекон' => 'https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5?w=200&h=200&fit=crop',
        'Картофель фри' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=200&h=200&fit=crop',
        'Луковые кольца' => 'https://images.unsplash.com/photo-1639024371703-4b00acab9225?w=200&h=200&fit=crop',
    ];

    public function run(): void
    {
        foreach (self::IMAGE_URLS_BY_NAME as $name => $imageUrl) {
            Dish::query()
                ->where('name', $name)
                ->where(function ($query): void {
                    $query->whereNull('image_url')->orWhere('image_url', '');
                })
                ->update(['image_url' => $imageUrl]);
        }
    }
}
