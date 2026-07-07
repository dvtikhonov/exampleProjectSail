<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_cart_items', function (Blueprint $table) {
            $table->index(['cart_id', 'dish_id']);
            $table->index(['cart_id', 'combo_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('max_cart_items', function (Blueprint $table) {
            $table->dropIndex(['cart_id', 'dish_id']);
            $table->dropIndex(['cart_id', 'combo_ref']);
        });
    }
};
