<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сессии синхронизации Briskly (proposals/approvals в JSON; токен — только в cache).
 */
return new class extends Migration
{
    /**
     * Создаёт таблицу briskly_sync_sessions.
     */
    public function up(): void
    {
        Schema::create('briskly_sync_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('created_by_max_user_id')->nullable();
            $table->unsignedBigInteger('vps_category_id')->nullable();
            $table->string('search_text', 120)->nullable();
            $table->text('clarification')->nullable();
            $table->string('status', 32);
            $table->json('briskly_snapshot')->nullable();
            $table->json('source_lines_snapshot')->nullable();
            $table->json('proposals')->nullable();
            $table->json('approvals')->nullable();
            $table->json('apply_report')->nullable();
            $table->json('allowed_briskly_category_ids')->nullable();
            $table->string('source_price_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index('created_by_max_user_id');
        });
    }

    /**
     * Откатывает таблицу briskly_sync_sessions.
     */
    public function down(): void
    {
        Schema::dropIfExists('briskly_sync_sessions');
    }
};
