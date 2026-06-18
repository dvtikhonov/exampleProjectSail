<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->index('user_id');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
            $table->dropIndex(['yandex_org_id']);
            $table->unique('yandex_org_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique(['yandex_org_id']);
            $table->index('yandex_org_id');
            $table->unique('user_id');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
        });
    }
};
