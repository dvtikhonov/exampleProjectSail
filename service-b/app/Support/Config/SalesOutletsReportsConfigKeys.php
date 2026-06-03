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

    public const MAX_MESSAGE_BOT_ACCESS_TOKEN = self::ROOT.'.types.max_message.bot_access_token';

    public const MAX_MESSAGE_CHAT_IDS = self::ROOT.'.types.max_message.chat_ids';

    public const MAX_MESSAGE_USER_IDS = self::ROOT.'.types.max_message.user_ids';

    public const MAX_MESSAGE_INTRO = self::ROOT.'.types.max_message.intro';

    public const MAX_MESSAGE_MAX_TEXT_LENGTH = self::ROOT.'.types.max_message.max_text_length';

    public const MAX_MESSAGE_API_RATE_LIMIT_RPS = self::ROOT.'.types.max_message.api_rate_limit_rps';

    public const MAX_MESSAGE_RATE_LIMIT_RETRY_MAX = self::ROOT.'.types.max_message.rate_limit_retry_max';

    public const MAX_MESSAGE_RATE_LIMIT_RETRY_DELAY_MS = self::ROOT.'.types.max_message.rate_limit_retry_delay_ms';

    public const MAX_MESSAGE_INTER_RECIPIENT_DELAY_MS = self::ROOT.'.types.max_message.inter_recipient_delay_ms';

    public const MAX_MESSAGE_ATTACHMENT_NOT_READY_RETRY_MAX = self::ROOT.'.types.max_message.attachment_not_ready_retry_max';

    public const MAX_MESSAGE_ATTACHMENT_NOT_READY_RETRY_DELAY_MS = self::ROOT.'.types.max_message.attachment_not_ready_retry_delay_ms';

    public static function fakeDelaySeconds(SalesOutletsReportType $reportType): string
    {
        return self::ROOT.'.types.'.$reportType->configKey().'.fake_delay_seconds';
    }
}
