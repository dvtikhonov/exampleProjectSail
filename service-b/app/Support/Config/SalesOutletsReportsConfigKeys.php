<?php

namespace App\Support\Config;

use App\Enums\SalesOutletsReportType;

final class SalesOutletsReportsConfigKeys
{
    private const ROOT = 'sales_outlets_reports';

    public const STORAGE_DISK = self::ROOT.'.storage_disk';

    public const APPLY_FAKE_DELAY_ENVIRONMENTS = self::ROOT.'.apply_fake_delay_environments';

    public const HTML_EMAIL_RECIPIENTS = self::ROOT.'.types.html_email.recipients';

    public const HTML_EMAIL_SUBJECT = self::ROOT.'.types.html_email.subject';

    public static function fakeDelaySeconds(SalesOutletsReportType $reportType): string
    {
        return self::ROOT.'.types.'.$reportType->configKey().'.fake_delay_seconds';
    }
}
