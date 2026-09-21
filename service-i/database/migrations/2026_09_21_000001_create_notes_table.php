<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создаёт таблицу notes.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('archived')->default(false);
            /** Legacy-поле: не отдаётся в API Resource. */
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Удаляет таблицу notes.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
