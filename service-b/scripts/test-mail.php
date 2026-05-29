<?php

use App\Mail\SalesOutletsReportMailable;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Mail::fake();

$mailable = new SalesOutletsReportMailable(
    reportSubject: 'Test',
    html: '<table><tr><td>shop</td></tr></table>',
);

Mail::to('test@example.com')->send($mailable);

echo "ok\n";
