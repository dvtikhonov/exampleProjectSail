<?php

use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_outlet_report_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('report_type', array_column(SalesOutletsReportType::cases(), 'value'))->index();
            $table->enum('status', array_column(AsyncJobStatus::cases(), 'value'))
                ->default(AsyncJobStatus::Pending->value)
                ->index();
            $table->json('filters');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_outlet_report_jobs');
    }
};
