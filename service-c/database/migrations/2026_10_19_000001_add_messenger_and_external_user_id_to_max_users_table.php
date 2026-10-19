<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Канал мессенджера в max_users (max|telegram) + external_user_id.
 *
 * Применять migrate только после явного согласия (см. migration.mdc
 * и docs/TELEGRAM_MINI_APP_OPS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_users', function (Blueprint $table): void {
            $table->string('messenger', 16)->default('max')->after('max_user_id');
            $table->unsignedBigInteger('external_user_id')->nullable()->after('messenger');
        });

        DB::table('max_users')->update([
            'messenger' => 'max',
        ]);

        DB::update('UPDATE max_users SET external_user_id = max_user_id WHERE external_user_id IS NULL');

        Schema::table('max_users', function (Blueprint $table): void {
            $table->unsignedBigInteger('external_user_id')->nullable(false)->change();
            $table->unique(
                ['messenger', 'external_user_id'],
                'max_users_messenger_external_user_id_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('max_users', function (Blueprint $table): void {
            $table->dropUnique('max_users_messenger_external_user_id_unique');
            $table->dropColumn(['messenger', 'external_user_id']);
        });
    }
};
