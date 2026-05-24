<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_outlets', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('approved')
                ->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('sales_outlets', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
            $table->dropSoftDeletes();
        });
    }
};
