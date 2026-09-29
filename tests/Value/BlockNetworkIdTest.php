<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Value;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Value\BlockNetworkId;
use PHPUnit\Framework\TestCase;

final class BlockNetworkIdTest extends TestCase
{
    public function testAirHashProjectsToBothWireRepresentations(): void
    {
        $air = BlockNetworkId::fromSigned(-604_749_536);

        self::assertSame(-604_749_536, $air->signed());
        self::assertSame(3_690_217_760, $air->unsigned());
        self::assertEquals($air, BlockNetworkId::fromUnsigned(3_690_217_760));
    }

    public function testFullThirtyTwoBitDomainAndBounds(): void
    {
        self::assertSame(0x80000000, BlockNetworkId::fromSigned(-0x80000000)->unsigned());
        self::assertSame(-1, BlockNetworkId::fromUnsigned(0xffffffff)->signed());
        self::assertSame(0x7fffffff, BlockNetworkId::fromSigned(0x7fffffff)->unsigned());

        foreach ([-0x80000001, 0x100000000] as $invalid) {
            try {
                BlockNetworkId::fromSigned($invalid);
                self::fail('Out-of-range signed block network ID was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
