<?php

use App\Enums\SalesOutletExportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_outlet_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('status', array_column(SalesOutletExportStatus::cases(), 'value'))
                ->default(SalesOutletExportStatus::Pending->value)
                ->index();
            $table->json('filters');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_outlet_export_jobs');
    }
};
