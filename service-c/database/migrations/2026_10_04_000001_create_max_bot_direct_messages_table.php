<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_bot_direct_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('max_user_id');
            $table->unsignedBigInteger('sender_max_user_id');
            $table->string('author_type', 16);
            $table->text('body');
            $table->bigInteger('chat_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->cascadeOnDelete();

            $table->foreign('sender_max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->cascadeOnDelete();

            $table->index(['max_user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_bot_direct_messages');
    }
};
