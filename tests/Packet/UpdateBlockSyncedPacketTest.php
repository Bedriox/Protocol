<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\ActorFlag;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\BlockSyncType;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\UpdateBlockFlag;
use Bedriox\Protocol\Packet\UpdateBlockSyncedPacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class UpdateBlockSyncedPacketTest extends TestCase
{
    private const string VECTOR = '027f04ac021301ac0202';

    public function testKnownVectorRoundTripsThroughRegistry(): void
    {
        $packet = new UpdateBlockSyncedPacket(
            new BlockPosition(1, -64, 2),
            300,
            [UpdateBlockFlag::Neighbors, UpdateBlockFlag::Network, UpdateBlockFlag::Priority],
            1,
            UnsignedLong::fromInt(300),
            BlockSyncType::DESTROY,
        );

        self::assertSame(self::VECTOR, bin2hex($packet->encode()));
        self::assertEquals($packet, UpdateBlockSyncedPacket::decode($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::UPDATE_BLOCK_SYNCED, $packet->encode()));
        self::assertSame(PacketIds::UPDATE_BLOCK_SYNCED, BedrockPacketCodec::packetId($packet));
        self::assertSame(34, ActorFlag::Moving->value);
    }

    public function testEveryTruncationAndTrailingByteFailsClosed(): void
    {
        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                UpdateBlockSyncedPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated synchronized block update was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectException(CodecException::class);
        UpdateBlockSyncedPacket::decode($wire . "\0");
    }

    public function testUnknownSyncTypeAndInvalidConstructionFailClosed(): void
    {
        $wire = hex2bin(self::VECTOR);
        self::assertIsString($wire);
        try {
            UpdateBlockSyncedPacket::decode(substr($wire, 0, -1) . "\x03");
            self::fail('Unknown synchronized block-update type was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidValueException::class);
        new UpdateBlockSyncedPacket(
            new BlockPosition(0, 0, 0),
            0,
            [UpdateBlockFlag::Network, UpdateBlockFlag::Network],
            0,
            UnsignedLong::fromInt(1),
            BlockSyncType::CREATE,
        );
    }
}
