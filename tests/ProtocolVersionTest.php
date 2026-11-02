<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\ProtocolVersion;

final class ProtocolVersionTest extends TestCase
{
    public function testPositiveVersionIsAccepted(): void
    {
        self::assertTrue((new ProtocolVersion(1))->equals(new ProtocolVersion(1)));
        self::assertFalse((new ProtocolVersion(1))->equals(new ProtocolVersion(2)));
        self::assertSame(2225, ProtocolVersion::CURRENT);
        self::assertSame('1.26.60', ProtocolVersion::GAME_VERSION);
        self::assertSame([2225], ProtocolVersion::SUPPORTED);
        self::assertFalse(ProtocolVersion::supports(2169));
        self::assertTrue(ProtocolVersion::supports(2225));
        self::assertFalse(ProtocolVersion::supports(2192));
        self::assertSame('1.26.60', ProtocolVersion::gameVersion(2225));
        $this->expectException(\InvalidArgumentException::class);
        ProtocolVersion::gameVersion(2169);
    }

    public function testNonPositiveVersionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProtocolVersion(0);
    }
}
