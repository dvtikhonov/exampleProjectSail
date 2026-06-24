<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_food_order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_order_id')->constrained('max_food_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_max_user_id');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sender_max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->cascadeOnDelete();

            $table->index(['food_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_food_order_messages');
    }
};
