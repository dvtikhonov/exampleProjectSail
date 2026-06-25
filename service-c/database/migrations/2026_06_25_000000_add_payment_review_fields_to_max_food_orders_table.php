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
            $table->string('payment_review_status', 32)->default('pending')->after('composition_review_status');
            $table->unsignedBigInteger('payment_reviewed_by')->nullable()->after('composition_reviewed_at');
            $table->timestamp('payment_reviewed_at')->nullable()->after('payment_reviewed_by');
            $table->text('payment_rejection_comment')->nullable()->after('composition_rejection_comment');

            $table->foreign('payment_reviewed_by')
                ->references('max_user_id')
                ->on('max_users')
                ->nullOnDelete();
        });

        DB::table('max_food_orders')
            ->whereIn('status', ['pending_review', 'awaiting_composition'])
            ->update([
                'payment_review_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        Schema::table('max_food_orders', function (Blueprint $table) {
            $table->dropForeign(['payment_reviewed_by']);
            $table->dropColumn([
                'payment_review_status',
                'payment_reviewed_by',
                'payment_reviewed_at',
                'payment_rejection_comment',
            ]);
        });
    }
};
