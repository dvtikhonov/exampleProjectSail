<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_dish_availability_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained('max_dishes')->cascadeOnDelete();
            $table->date('available_date');
            $table->timestamps();

            $table->unique(['dish_id', 'available_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_dish_availability_dates');
    }
};
