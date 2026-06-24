<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_food_order_chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_order_id')->constrained('max_food_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('reader_max_user_id');
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->constrained('max_food_order_messages')
                ->nullOnDelete();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('reader_max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->cascadeOnDelete();

            $table->unique(
                ['food_order_id', 'reader_max_user_id'],
                'food_order_chat_reads_order_reader_uq',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_food_order_chat_reads');
    }
};
