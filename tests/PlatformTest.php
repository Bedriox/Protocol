<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests;

use PHPUnit\Framework\TestCase;

final class PlatformTest extends TestCase
{
    public function testRuntimeUsesSixtyFourBitIntegers(): void
    {
        self::assertSame(8, PHP_INT_SIZE, 'Bedriox/Protocol requires 64-bit PHP integers.');
    }
}
