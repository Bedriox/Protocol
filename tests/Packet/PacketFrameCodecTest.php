<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\PacketFrame;
use Bedriox\Protocol\Packet\PacketFrameCodec;
use Bedriox\Protocol\Packet\PacketHeader;

final class PacketFrameCodecTest extends TestCase
{
    public function testIndependentHeaderVectorsAndRoundTrip(): void
    {
        $minimum = new PacketFrame(new PacketHeader(1), "\xaa");
        self::assertSame('01aa', bin2hex(PacketFrameCodec::encode($minimum, 16)));

        $routed = new PacketFrame(new PacketHeader(0x123, 2, 1), "\xde\xad");
        $encoded = PacketFrameCodec::encode($routed, 16);
        self::assertSame('a332dead', bin2hex($encoded));
        self::assertEquals($routed, PacketFrameCodec::decode($encoded, 16));

        $maximum = new PacketFrame(new PacketHeader(0x3ff, 3, 3), '');
        self::assertSame('ff7f', bin2hex(PacketFrameCodec::encode($maximum, 2)));
    }

    public function testHeaderAndCapacityLimitsAreDeterministic(): void
    {
        foreach (
            [
                static fn(): PacketHeader => new PacketHeader(-1),
                static fn(): PacketHeader => new PacketHeader(0x400),
                static fn(): PacketHeader => new PacketHeader(0, -1),
                static fn(): PacketHeader => new PacketHeader(0, 0, 4),
            ] as $factory
        ) {
            try {
                $factory();
                self::fail('Invalid packet header was accepted.');
            } catch (InvalidValueException) {
            }
        }

        $this->expectException(BufferOverflowException::class);
        PacketFrameCodec::encode(new PacketFrame(new PacketHeader(1), 'x'), 1);
    }

    public function testMalformedHeadersRejectTruncationNoncanonicalAndReservedBits(): void
    {
        foreach (["\x80", "\x81\x00", "\x80\x80\x01"] as $bytes) {
            try {
                PacketFrameCodec::decode($bytes, 16);
                self::fail('Malformed packet header was accepted.');
            } catch (CodecException) {
            }
        }

        $this->expectException(MalformedDataException::class);
        PacketFrameCodec::decode("\x80\x80\x01", 16);
    }

    public function testValuesAreImmutable(): void
    {
        self::assertTrue(new ReflectionProperty(PacketHeader::class, 'packetId')->isReadOnly());
        self::assertTrue(new ReflectionProperty(PacketFrame::class, 'payload')->isReadOnly());
    }
}
