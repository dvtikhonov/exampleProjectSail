<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Infrastructure\Laravel\RandomBytesSchedulePhotoIdGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RandomBytesSchedulePhotoIdGeneratorTest extends TestCase
{
    #[Test]
    public function generate_returns_distinct_uuid_v4(): void
    {
        $generator = new RandomBytesSchedulePhotoIdGenerator;
        $first = $generator->generate();
        $second = $generator->generate();

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        $this->assertMatchesRegularExpression($pattern, $first);
        $this->assertMatchesRegularExpression($pattern, $second);
        $this->assertNotSame($first, $second);
    }
}
