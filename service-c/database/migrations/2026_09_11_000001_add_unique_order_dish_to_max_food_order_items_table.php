<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Уникальность (order_id, dish_id) для upsert в max_food_order_items.
 *
 * Перед индексом удаляет дубликаты (оставляет строку с max(id) в группе).
 * Нужна, если таблица уже создана старой версией create-миграции без unique.
 * Применение на shared/prod — только после отдельного согласия.
 */
return new class extends Migration
{
    private const INDEX = 'max_food_order_items_order_id_dish_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('max_food_order_items')) {
            return;
        }

        if ($this->hasUniqueIndex()) {
            return;
        }

        $this->deleteDuplicateOrderDishRows();

        Schema::table('max_food_order_items', function (Blueprint $table): void {
            $table->unique(['order_id', 'dish_id'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('max_food_order_items')) {
            return;
        }

        if (! $this->hasUniqueIndex()) {
            return;
        }

        Schema::table('max_food_order_items', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }

    /**
     * Оставляет одну строку на (order_id, dish_id) — с наибольшим id.
     */
    private function deleteDuplicateOrderDishRows(): void
    {
        DB::statement(<<<'SQL'
            DELETE items FROM max_food_order_items AS items
            INNER JOIN (
                SELECT order_id, dish_id, MAX(id) AS keep_id
                FROM max_food_order_items
                WHERE dish_id IS NOT NULL
                GROUP BY order_id, dish_id
                HAVING COUNT(*) > 1
            ) AS dupes
                ON dupes.order_id = items.order_id
                AND dupes.dish_id = items.dish_id
                AND items.id <> dupes.keep_id
        SQL);
    }

    private function hasUniqueIndex(): bool
    {
        foreach (Schema::getIndexes('max_food_order_items') as $index) {
            if (($index['name'] ?? null) === self::INDEX) {
                return true;
            }
        }

        return false;
    }
};
