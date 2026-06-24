<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_food_orders', function (Blueprint $table) {
            $table->string('address_review_status', 32)->default('pending')->after('status');
            $table->string('composition_review_status', 32)->default('not_applicable')->after('address_review_status');
            $table->unsignedBigInteger('address_reviewed_by')->nullable()->after('composition_review_status');
            $table->timestamp('address_reviewed_at')->nullable()->after('address_reviewed_by');
            $table->unsignedBigInteger('composition_reviewed_by')->nullable()->after('address_reviewed_at');
            $table->timestamp('composition_reviewed_at')->nullable()->after('composition_reviewed_by');
            $table->text('address_rejection_comment')->nullable()->after('composition_reviewed_at');
            $table->text('composition_rejection_comment')->nullable()->after('address_rejection_comment');

            $table->foreign('address_reviewed_by')
                ->references('max_user_id')
                ->on('max_users')
                ->nullOnDelete();

            $table->foreign('composition_reviewed_by')
                ->references('max_user_id')
                ->on('max_users')
                ->nullOnDelete();
        });

        DB::table('max_food_orders')
            ->where('status', 'submitted')
            ->update([
                'status' => 'pending_review',
                'address_review_status' => 'pending',
                'composition_review_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        DB::table('max_food_orders')
            ->where('status', 'pending_review')
            ->update([
                'status' => 'submitted',
            ]);

        Schema::table('max_food_orders', function (Blueprint $table) {
            $table->dropForeign(['address_reviewed_by']);
            $table->dropForeign(['composition_reviewed_by']);
            $table->dropColumn([
                'address_review_status',
                'composition_review_status',
                'address_reviewed_by',
                'address_reviewed_at',
                'composition_reviewed_by',
                'composition_reviewed_at',
                'address_rejection_comment',
                'composition_rejection_comment',
            ]);
        });
    }
};
