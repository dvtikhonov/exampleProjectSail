<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_users', function (Blueprint $table) {
            $table->text('delivery_address')->nullable()->after('customer_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('max_users', function (Blueprint $table) {
            $table->dropColumn('delivery_address');
        });
    }
};
