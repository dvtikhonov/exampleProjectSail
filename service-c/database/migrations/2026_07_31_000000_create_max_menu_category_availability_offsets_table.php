<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_menu_category_availability_offsets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')
                ->constrained('max_menu_categories')
                ->cascadeOnDelete();
            $table->uuid('group_key');
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('offset_days');
            $table->timestamps();

            $table->unique(['menu_category_id', 'weekday'], 'mmcao_category_weekday_unique');
            $table->index(['menu_category_id', 'group_key'], 'mmcao_category_group_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_menu_category_availability_offsets');
    }
};
