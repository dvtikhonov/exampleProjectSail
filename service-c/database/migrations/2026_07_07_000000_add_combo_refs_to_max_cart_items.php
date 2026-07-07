<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_cart_items', function (Blueprint $table) {
            $table->index('cart_id', 'max_cart_items_cart_id_index');
        });

        Schema::table('max_cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'dish_id']);

            $table->string('combo_ref', 36)->nullable()->after('quantity');
            $table->foreignId('combo_partner_dish_id')
                ->nullable()
                ->after('combo_ref')
                ->constrained('max_dishes');

            $table->index('combo_ref');
        });
    }

    public function down(): void
    {
        Schema::table('max_cart_items', function (Blueprint $table) {
            $table->dropForeign(['combo_partner_dish_id']);
            $table->dropIndex(['combo_ref']);
            $table->dropColumn(['combo_ref', 'combo_partner_dish_id']);

            $table->unique(['cart_id', 'dish_id']);
        });

        Schema::table('max_cart_items', function (Blueprint $table) {
            $table->dropIndex('max_cart_items_cart_id_index');
        });
    }
};
