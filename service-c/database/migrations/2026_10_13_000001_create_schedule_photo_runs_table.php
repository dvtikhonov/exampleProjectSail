<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Прогоны SchedulePhoto (фото графика → OCR → match → apply).
 *
 * Применять migrate только после явного согласия (см. migration.mdc).
 */
return new class extends Migration
{
    /**
     * Создаёт таблицу schedule_photo_runs.
     */
    public function up(): void
    {
        Schema::create('schedule_photo_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 32);
            $table->string('base_url', 500);
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedBigInteger('restaurant_id');
            $table->json('category_ids');
            $table->unsignedBigInteger('created_by_max_user_id');
            $table->string('photo_path', 500)->nullable();
            $table->json('match_result')->nullable();
            $table->json('discarded')->nullable();
            $table->string('error_message', 2000)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index('created_by_max_user_id');
        });
    }

    /**
     * Откатывает таблицу schedule_photo_runs.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_photo_runs');
    }
};
