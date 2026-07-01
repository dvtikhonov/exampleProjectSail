<?php

/** Журнал переходов по коротким ссылкам (IP + время визита). */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_link_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('short_link_id');
            $table->string('ip_address', 45);
            $table->timestamp('visited_at');

            $table->index(['short_link_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_link_clicks');
    }
};
