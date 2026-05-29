<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\Mail\SalesOutletsReportMailable;
use Illuminate\Support\Facades\Mail;

class LaravelReportMailSender implements ReportMailSenderInterface
{
    public function send(array $recipients, string $subject, string $html): void
    {
        Mail::to($recipients)->send(new SalesOutletsReportMailable(
            subjectLine: $subject,
            htmlContent: $html,
        ));
    }
}
