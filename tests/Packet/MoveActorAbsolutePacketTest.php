<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\MoveActorAbsoluteFlag;
use Bedriox\Protocol\Packet\MoveActorAbsolutePacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Value\UnsignedLong;

final class MoveActorAbsolutePacketTest extends TestCase
{
    private const string VECTOR = 'ac02090000803f000000c000006040204080';

    public function testProtocol2193LiteralVectorAndTypedGroundState(): void
    {
        $packet = new MoveActorAbsolutePacket(
            UnsignedLong::fromInt(300),
            1.0, -2.0, 3.5,
            45.0, 90.0, 180.0,
            [MoveActorAbsoluteFlag::OnGround, MoveActorAbsoluteFlag::ForceCompletion],
        );

        self::assertSame(PacketIds::MOVE_ACTOR_ABSOLUTE, $packet->packetId());
        self::assertSame(PacketIds::MOVE_ACTOR_ABSOLUTE, BedrockPacketCodec::packetId($packet));
        self::assertTrue($packet->onGround());
        self::assertTrue($packet->hasFlag(MoveActorAbsoluteFlag::ForceCompletion));
        self::assertFalse($packet->hasFlag(MoveActorAbsoluteFlag::Teleported));
        self::assertSame(self::VECTOR, bin2hex(BedrockPacketCodec::encode($packet)));

        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::MOVE_ACTOR_ABSOLUTE, $wire));
    }

    public function testAnglesWrapIntoOneByteWithoutChangingFiniteCoordinates(): void
    {
        $packet = new MoveActorAbsolutePacket(
            UnsignedLong::fromInt(1),
            -0.0, 0.0, 0.0,
            -90.0, 360.0, 720.0,
        );

        self::assertSame('0100000000800000000000000000c00000', bin2hex($packet->encode()));
        $decoded = MoveActorAbsolutePacket::decode($packet->encode());
        self::assertSame(270.0, $decoded->pitch);
        self::assertSame(0.0, $decoded->yaw);
        self::assertSame(0.0, $decoded->headYaw);
        self::assertFalse($decoded->onGround());
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPayloads(): iterable
    {
        yield 'empty' => [''];
        yield 'runtime ID truncated' => ["\x80"];
        yield 'unknown header bit' => ["\x00\x10" . str_repeat("\x00", 15)];
        yield 'coordinate truncated' => ["\x00\x00" . str_repeat("\x00", 11)];
        yield 'rotation truncated' => ["\x00\x00" . str_repeat("\x00", 14)];
        yield 'non-finite coordinate' => ["\x00\x00\x00\x00\xc0\x7f" . str_repeat("\x00", 11)];
        yield 'trailing data' => ["\x00\x00" . str_repeat("\x00", 16)];
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedPayloadFailsClosed(string $payload): void
    {
        $this->expectException(CodecException::class);
        MoveActorAbsolutePacket::decode($payload);
    }

    public function testConstructorRejectsInvalidFlagsAndNonFiniteValues(): void
    {
        $rejections = 0;
        foreach ([
            static fn (): MoveActorAbsolutePacket => new MoveActorAbsolutePacket(
                UnsignedLong::fromInt(1), 0.0, 0.0, INF, 0.0, 0.0, 0.0,
            ),
            static fn (): MoveActorAbsolutePacket => new MoveActorAbsolutePacket(
                UnsignedLong::fromInt(1), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
                [MoveActorAbsoluteFlag::OnGround, MoveActorAbsoluteFlag::OnGround],
            ),
            static fn (): object => (new \ReflectionClass(MoveActorAbsolutePacket::class))->newInstanceArgs([
                UnsignedLong::fromInt(1), 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
                [MoveActorAbsoluteFlag::OnGround, 'not-a-flag'],
            ]),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid move-actor state was accepted.');
            } catch (InvalidValueException) {
                ++$rejections;
            }
        }
        self::assertSame(3, $rejections);
    }
}
