<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE organizations MODIFY ratings_count INT UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE organizations MODIFY reviews_count INT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE organizations MODIFY ratings_count INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE organizations MODIFY reviews_count INT UNSIGNED NOT NULL DEFAULT 0');
    }
};
