<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_dishes', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->decimal('weight', 10, 3)->nullable()->after('description');
            $table->string('weight_unit', 8)->nullable()->after('weight');
            $table->unsignedTinyInteger('vat_rate')->nullable()->after('price');
        });

        DB::table('max_dishes')
            ->where('image_url', 'like', 'http%')
            ->update(['image_url' => null]);
    }

    public function down(): void
    {
        Schema::table('max_dishes', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'weight',
                'weight_unit',
                'vat_rate',
            ]);
        });
    }
};
