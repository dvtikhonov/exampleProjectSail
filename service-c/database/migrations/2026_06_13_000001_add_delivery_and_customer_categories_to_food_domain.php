<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_customer_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('max_restaurant_category_delivery_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')
                ->constrained('max_restaurants', 'id', 'max_rcdt_restaurant_id_fk')
                ->cascadeOnDelete();
            $table->foreignId('customer_category_id')
                ->constrained('max_customer_categories', 'id', 'max_rcdt_category_id_fk')
                ->cascadeOnDelete();
            $table->decimal('min_items_total', 10, 2);
            $table->decimal('delivery_cost', 10, 2);
            $table->timestamps();

            $table->unique(
                ['restaurant_id', 'customer_category_id', 'min_items_total'],
                'max_rcdt_restaurant_category_min_unique',
            );
        });

        Schema::table('max_users', function (Blueprint $table) {
            $table->foreignId('customer_category_id')
                ->nullable()
                ->after('photo_url')
                ->constrained('max_customer_categories')
                ->nullOnDelete();
        });

        Schema::table('max_carts', function (Blueprint $table) {
            $table->text('delivery_address')->nullable()->after('status');
        });

        Schema::table('max_food_orders', function (Blueprint $table) {
            $table->text('delivery_address')->nullable()->after('total');
            $table->decimal('delivery_cost', 10, 2)->nullable()->after('delivery_address');
            $table->decimal('items_total', 10, 2)->nullable()->after('delivery_cost');
        });

        DB::table('max_food_orders')->update([
            'items_total' => DB::raw('total'),
            'delivery_address' => '',
            'delivery_cost' => null,
        ]);

        DB::statement('ALTER TABLE max_food_orders MODIFY delivery_address TEXT NOT NULL');
        DB::statement('ALTER TABLE max_food_orders MODIFY items_total DECIMAL(10, 2) NOT NULL');
    }

    public function down(): void
    {
        Schema::table('max_food_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_address', 'delivery_cost', 'items_total']);
        });

        Schema::table('max_carts', function (Blueprint $table) {
            $table->dropColumn('delivery_address');
        });

        Schema::table('max_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_category_id');
        });

        Schema::dropIfExists('max_restaurant_category_delivery_tiers');
        Schema::dropIfExists('max_customer_categories');
    }
};
