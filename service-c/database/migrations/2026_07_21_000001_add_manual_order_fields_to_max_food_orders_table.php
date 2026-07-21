<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_food_orders', function (Blueprint $table) {
            $table->boolean('is_manual')
                ->default(false)
                ->after('max_user_id');

            $table->unsignedBigInteger('created_by_max_user_id')
                ->nullable()
                ->after('is_manual');

            $table->foreign('created_by_max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->nullOnDelete();

            $table->index('is_manual', 'max_food_orders_is_manual_index');
        });
    }

    public function down(): void
    {
        Schema::table('max_food_orders', function (Blueprint $table) {
            $table->dropIndex('max_food_orders_is_manual_index');
            $table->dropForeign(['created_by_max_user_id']);
            $table->dropColumn(['is_manual', 'created_by_max_user_id']);
        });
    }
};
