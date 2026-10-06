<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\EndCrystalActorMetadata;
use Bedriox\Protocol\Packet\SetActorDataPacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class EndCrystalActorMetadataTest extends TestCase
{
    public function testBeamTargetUsesCurrentLiteralIdAndBlockPositionType(): void
    {
        $metadata = EndCrystalActorMetadata::beamTarget(new BlockPosition(-3, 128, 5));

        self::assertSame(47, $metadata->id);
        self::assertSame(ActorMetadata::TYPE_BLOCK_POSITION, $metadata->type);
        self::assertEquals(new BlockPosition(-3, 128, 5), $metadata->value);
    }

    public function testSetActorDataKnownVectorAndRoundTrip(): void
    {
        $packet = new SetActorDataPacket(
            UnsignedLong::fromInt(42),
            UnsignedLong::fromInt(7),
            [EndCrystalActorMetadata::beamTarget(new BlockPosition(-3, 128, 5))],
        );

        $wire = hex2bin('2a012f06060580020a000007');
        self::assertIsString($wire);
        self::assertSame($wire, $packet->encode());
        self::assertEquals($packet, SetActorDataPacket::decode($wire));

        for ($length = 0, $maximum = strlen($wire); $length < $maximum; ++$length) {
            try {
                SetActorDataPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated End Crystal metadata at {$length} bytes was accepted.");
            } catch (CodecException) {
            }
        }
    }
}
