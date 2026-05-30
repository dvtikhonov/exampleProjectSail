<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sales_outlet_export_jobs');
        Schema::dropIfExists('sales_outlet_mail_jobs');
    }

    public function down(): void
    {
        // Legacy tables are intentionally not recreated.
    }
};
