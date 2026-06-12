<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('max_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('max_restaurants')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('max_dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained('max_menu_categories')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('max_carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('max_user_id');
            $table->foreignId('restaurant_id')->constrained('max_restaurants');
            $table->string('status', 32);
            $table->timestamps();

            $table->foreign('max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->cascadeOnDelete();

            $table->index(['max_user_id', 'status']);
        });

        Schema::create('max_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('max_carts')->cascadeOnDelete();
            $table->foreignId('dish_id')->constrained('max_dishes');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['cart_id', 'dish_id']);
        });

        Schema::create('max_food_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('max_carts');
            $table->unsignedBigInteger('max_user_id');
            $table->foreignId('restaurant_id')->constrained('max_restaurants');
            $table->string('status', 32);
            $table->decimal('total', 10, 2);
            $table->json('items_snapshot');
            $table->timestamps();

            $table->foreign('max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_food_orders');
        Schema::dropIfExists('max_cart_items');
        Schema::dropIfExists('max_carts');
        Schema::dropIfExists('max_dishes');
        Schema::dropIfExists('max_menu_categories');
        Schema::dropIfExists('max_restaurants');
    }
};
