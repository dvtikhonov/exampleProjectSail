<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_carts', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_max_user_id')
                ->nullable()
                ->after('max_user_id');

            $table->foreign('created_by_max_user_id')
                ->references('max_user_id')
                ->on('max_users')
                ->nullOnDelete();

            $table->index(
                ['max_user_id', 'created_by_max_user_id', 'status'],
                'max_carts_user_creator_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('max_carts', function (Blueprint $table) {
            $table->dropIndex('max_carts_user_creator_status_index');
            $table->dropForeign(['created_by_max_user_id']);
            $table->dropColumn('created_by_max_user_id');
        });
    }
};
