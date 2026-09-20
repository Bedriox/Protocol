<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\SetActorMotionPacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class SetActorMotionPacketTest extends TestCase
{
    private const string VECTOR = '070000c03f000080be00000000';

    public function testMatchesIndependentCurrentProtocolVector(): void
    {
        $packet = new SetActorMotionPacket(UnsignedLong::fromInt(7), 1.5, -0.25, 0.0);
        self::assertSame(self::VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::SET_ACTOR_MOTION, hex2bin(self::VECTOR) ?: ''));
        self::assertSame(PacketIds::SET_ACTOR_MOTION, BedrockPacketCodec::packetId($packet));
    }

    public function testRejectsNonFiniteConstruction(): void
    {
        $this->expectException(InvalidValueException::class);
        new SetActorMotionPacket(UnsignedLong::fromInt(1), NAN, 0.0, 0.0);
    }

    public function testRejectsEveryTruncationAndTrailingData(): void
    {
        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                SetActorMotionPacket::decode(substr($wire, 0, $length));
                self::fail('Truncated actor motion was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectException(CodecException::class);
        SetActorMotionPacket::decode($wire . "\0");
    }
}
