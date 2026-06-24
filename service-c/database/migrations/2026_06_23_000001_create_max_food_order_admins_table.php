<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_food_order_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('max_user_id');
            $table->string('role', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->cascadeOnDelete();

            $table->unique(['max_user_id', 'role'], 'max_food_order_admins_user_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_food_order_admins');
    }
};
