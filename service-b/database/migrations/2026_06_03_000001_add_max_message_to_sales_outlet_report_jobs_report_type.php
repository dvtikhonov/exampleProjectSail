<?php

use App\Enums\SalesOutletsReportType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $values = implode("','", array_column(SalesOutletsReportType::cases(), 'value'));

        DB::statement(
            "ALTER TABLE sales_outlet_report_jobs MODIFY report_type ENUM('{$values}') NOT NULL"
        );
    }

    public function down(): void
    {
        $values = implode("','", [
            SalesOutletsReportType::CsvDownload->value,
            SalesOutletsReportType::HtmlEmail->value,
        ]);

        DB::statement(
            "ALTER TABLE sales_outlet_report_jobs MODIFY report_type ENUM('{$values}') NOT NULL"
        );
    }
};
