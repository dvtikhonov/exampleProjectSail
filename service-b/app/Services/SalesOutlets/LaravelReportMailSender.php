<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\Mail\SalesOutletsReportMailable;
use Illuminate\Contracts\Mail\Mailer;

class LaravelReportMailSender implements ReportMailSenderInterface
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {}

    public function send(array $recipients, string $subject, string $html): void
    {
        $this->mailer->to($recipients)->send(new SalesOutletsReportMailable(
            subjectLine: $subject,
            htmlContent: $html,
        ));
    }
}
