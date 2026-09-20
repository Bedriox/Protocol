<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\MovementPredictionSyncPacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MovementPredictionSyncPacketTest extends TestCase
{
    private const string RETAIL_SHAPED_VECTOR = '8a80a1808081e0019a99193f6666e63f9a99193fcdcccc3d0ad7a33c0ad7a33c3d0ad73e0000a0410000a0410000a03f000020c0000070400700';

    public function testRetailShapedFiftyEightByteVectorIsRegisteredAndRoundTrips(): void
    {
        $wire = hex2bin(self::RETAIL_SHAPED_VECTOR);
        self::assertIsString($wire);
        self::assertSame(58, strlen($wire));

        $packet = new MovementPredictionSyncPacket(
            [1, 3, 14, 19, 35, 47, 48, 49],
            0.6, 1.8, 0.6,
            0.1, 0.02, 0.02, 0.42,
            20.0, 20.0,
            1.25, -2.5, 3.75,
            UnsignedLong::fromInt(7),
            false,
        );

        self::assertSame(self::RETAIL_SHAPED_VECTOR, bin2hex($packet->encode()));
        self::assertSame(PacketIds::MOVEMENT_PREDICTION_SYNC, $packet->packetId());
        self::assertSame(PacketIds::MOVEMENT_PREDICTION_SYNC, BedrockPacketCodec::packetId($packet));
        $decoded = BedrockPacketCodec::decode(PacketIds::MOVEMENT_PREDICTION_SYNC, $wire);
        self::assertInstanceOf(MovementPredictionSyncPacket::class, $decoded);
        self::assertSame($wire, $decoded->encode());
    }

    public function testFlagsAcrossTheCurrentSchemaAndUnsignedRuntimeDomainRoundTrip(): void
    {
        $packet = new MovementPredictionSyncPacket(
            [0, 63, 64, 126, 127, 130],
            0.6, 1.8, 0.6,
            0.1, 0.02, 0.02, 0.42,
            20.0, 20.0,
            0.0, 0.0, 0.0,
            new UnsignedLong(0xffffffff, 0xffffffff),
            true,
        );

        $decoded = MovementPredictionSyncPacket::decode($packet->encode());
        self::assertSame($packet->encode(), $decoded->encode());
        self::assertSame([0, 63, 64, 126, 127, 130], $decoded->actorFlags);
        self::assertSame(0xffffffff, $decoded->runtimeActorId->high);
        self::assertSame(0xffffffff, $decoded->runtimeActorId->low);
        self::assertTrue($decoded->flying);
    }

    public function testEveryRetailShapedTruncationAndTrailingByteFailClosed(): void
    {
        $wire = hex2bin(self::RETAIL_SHAPED_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                MovementPredictionSyncPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated movement-prediction sync was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        MovementPredictionSyncPacket::decode($wire . "\0");
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedWireValuesFailClosed(string $wire): void
    {
        $this->expectException(CodecException::class);
        MovementPredictionSyncPacket::decode($wire);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPayloads(): iterable
    {
        $wire = hex2bin(self::RETAIL_SHAPED_VECTOR);
        self::assertIsString($wire);
        yield 'noncanonical flags' => ["\x80\x00" . substr($wire, 8)];
        yield 'flags exceed current schema' => [str_repeat("\x80", 18) . "\x20" . substr($wire, 8)];
        yield 'unterminated bounded flags' => [str_repeat("\x80", 19) . substr($wire, 8)];
        yield 'non-finite bounding box' => [substr($wire, 0, 8) . pack('g', INF) . substr($wire, 12)];
        yield 'non-finite unknown field' => [substr($wire, 0, 48) . pack('g', NAN) . substr($wire, 52)];
        yield 'invalid flying boolean' => [substr($wire, 0, -1) . "\x02"];
        yield 'noncanonical runtime actor ID' => [substr($wire, 0, -2) . "\x87\x00\x00"];
    }

    /** @param array<array-key, mixed> $flags */
    #[DataProvider('invalidConstruction')]
    public function testInvalidValuesCannotBeConstructed(array $flags, float $speed): void
    {
        $this->expectException(InvalidValueException::class);
        new MovementPredictionSyncPacket(
            $flags,
            0.6, 1.8, 0.6,
            $speed, 0.02, 0.02, 0.42,
            20.0, 20.0,
            0.0, 0.0, 0.0,
            UnsignedLong::fromInt(1),
            false,
        );
    }

    /** @return iterable<string, array{array<array-key, mixed>, float}> */
    public static function invalidConstruction(): iterable
    {
        yield 'non-list flags' => [[1 => 1], 0.1];
        yield 'non-integer flag' => [['1'], 0.1];
        yield 'duplicate flags' => [[1, 1], 0.1];
        yield 'unordered flags' => [[2, 1], 0.1];
        yield 'negative flag' => [[-1], 0.1];
        yield 'flag beyond current schema' => [[MovementPredictionSyncPacket::ACTOR_FLAG_COUNT], 0.1];
        yield 'non-finite float' => [[], INF];
    }
}
