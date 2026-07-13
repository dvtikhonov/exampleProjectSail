<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создаёт таблицу tasks.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', array_column(TaskStatus::cases(), 'value'))
                ->default(TaskStatus::Pending->value);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due_date']);
        });
    }

    /**
     * Удаляет таблицу tasks.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
