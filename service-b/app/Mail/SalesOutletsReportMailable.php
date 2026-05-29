<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalesOutletsReportMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $subjectLine,
        private readonly string $htmlContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sales-outlets-report',
            with: [
                'htmlContent' => $this->htmlContent,
            ],
        );
    }
}
