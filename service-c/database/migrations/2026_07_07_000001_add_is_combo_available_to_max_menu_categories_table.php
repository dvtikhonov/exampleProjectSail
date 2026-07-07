<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_menu_categories', function (Blueprint $table) {
            $table->boolean('is_combo_available')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('max_menu_categories', function (Blueprint $table) {
            $table->dropColumn('is_combo_available');
        });
    }
};
