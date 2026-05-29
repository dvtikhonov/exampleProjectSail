<?php

namespace Tests\Feature;

use App\Mail\SalesOutletsReportMailable;
use Tests\TestCase;

class DebugMailRenderTest extends TestCase
{
    public function test_mailable_instantiates(): void
    {
        $mailable = new SalesOutletsReportMailable(
            subjectLine: 'Test',
            htmlContent: '<table><tr><td>shop</td></tr></table>',
        );

        $this->assertSame('Test', $mailable->envelope()->subject);
        $this->assertStringContainsString('shop', $mailable->render());
    }
}
