<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_outlets', function (Blueprint $table): void {
            $table->id();
            $table->string('shop')->index();
            $table->string('manager')->index();
            $table->string('curator')->index();
            $table->string('name')->index();
            $table->string('inn')->index();
            $table->string('head_organization')->index();
            $table->enum('head_organization_type', array_column(HeadOrganizationType::cases(), 'value'))->index();
            $table->string('organization_name')->index();
            $table->enum('status', array_column(SalesOutletStatus::cases(), 'value'))->index();
            $table->string('approved', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_outlets');
    }
};
