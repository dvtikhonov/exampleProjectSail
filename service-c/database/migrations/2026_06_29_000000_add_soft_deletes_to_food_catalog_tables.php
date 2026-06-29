<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_restaurants', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('max_menu_categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('max_dishes', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('max_customer_categories', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('max_customer_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('max_dishes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('max_menu_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('max_restaurants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
