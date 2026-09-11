<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Нормализованные позиции выполненных заказов для отчётов (вариант B).
 *
 * Применение на shared/prod — только после отдельного согласия.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_food_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('max_food_orders')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('restaurant_id');
            $table->date('report_date');
            $table->unsignedBigInteger('dish_id')->nullable();
            $table->string('dish_name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->unique(['order_id', 'dish_id'], 'max_food_order_items_order_id_dish_id_unique');
            $table->index(['restaurant_id', 'report_date']);
            $table->index(['report_date', 'dish_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_food_order_items');
    }
};
