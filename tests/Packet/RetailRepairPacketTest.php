<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\EmoteFlag;
use Bedriox\Protocol\Packet\EmotePacket;
use Bedriox\Protocol\Packet\LevelEventPacket;
use Bedriox\Protocol\Packet\LevelEventPosition;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\UpdateBlockFlag;
use Bedriox\Protocol\Packet\UpdateBlockPacket;
use Bedriox\Protocol\Value\UnsignedLong;

final class RetailRepairPacketTest extends TestCase
{
    private const string EMOTE_VECTOR = 'ac02016514023432017003';
    private const string LEVEL_EVENT_VECTOR = 'a0380000c03f000000c00000804201';
    private const string UPDATE_BLOCK_VECTOR = '027f04ac021301';

    public function testIndependentLiteralVectorsRoundTripAndAreRegistered(): void
    {
        $packets = [
            new EmotePacket(
                UnsignedLong::fromInt(300),
                'e',
                20,
                '42',
                'p',
                [EmoteFlag::ServerSide, EmoteFlag::MuteEmoteChat],
            ),
            new LevelEventPacket(3600, new LevelEventPosition(1.5, -2.0, 64.0), -1),
            new UpdateBlockPacket(
                new BlockPosition(1, -64, 2),
                300,
                [UpdateBlockFlag::Neighbors, UpdateBlockFlag::Network, UpdateBlockFlag::Priority],
                1,
            ),
        ];
        $vectors = [self::EMOTE_VECTOR, self::LEVEL_EVENT_VECTOR, self::UPDATE_BLOCK_VECTOR];
        $ids = [PacketIds::EMOTE, PacketIds::LEVEL_EVENT, PacketIds::UPDATE_BLOCK];
        foreach ($packets as $index => $packet) {
            self::assertSame($vectors[$index], bin2hex($packet->encode()));
            self::assertEquals($packet, BedrockPacketCodec::decode($ids[$index], $packet->encode()));
            self::assertSame($ids[$index], BedrockPacketCodec::packetId($packet));
        }
    }

    public function testEveryMeaningfulTruncationAndTrailingByteFailClosed(): void
    {
        foreach ([self::EMOTE_VECTOR, self::LEVEL_EVENT_VECTOR, self::UPDATE_BLOCK_VECTOR] as $hex) {
            $wire = hex2bin($hex);
            self::assertIsString($wire);
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    $this->decodeByVector($hex, substr($wire, 0, $length));
                    self::fail("Truncated retail-repair packet was accepted at {$length} bytes.");
                } catch (CodecException) {
                    self::addToAssertionCount(1);
                }
            }
            try {
                $this->decodeByVector($hex, $wire . "\0");
                self::fail('Trailing packet byte was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testScalarBoundariesAndEveryKnownFlagRoundTrip(): void
    {
        $emote = new EmotePacket(UnsignedLong::fromInt(0), '', 0xffffffff, '', '', EmoteFlag::cases());
        self::assertEquals($emote, EmotePacket::decode($emote->encode()));

        $levelEvent = new LevelEventPacket(-0x80000000, new LevelEventPosition(0.0, 0.0, 0.0), 0x7fffffff);
        self::assertEquals($levelEvent, LevelEventPacket::decode($levelEvent->encode()));

        $update = new UpdateBlockPacket(
            new BlockPosition(-0x80000000, 0x7fffffff, 0),
            -0x80000000,
            UpdateBlockFlag::cases(),
            0xffffffff,
        );
        self::assertEquals($update, UpdateBlockPacket::decode($update->encode()));
    }

    public function testEmoteBoundsEncodingAndFlagsFailClosed(): void
    {
        foreach ([
            "\0\1\xff", // invalid UTF-8 emote ID
            "\0\x81\x20", // emote ID length 4097 exceeds the bound
            "\0\0\0\0\0\x04", // unknown flag bit
        ] as $wire) {
            try {
                EmotePacket::decode($wire);
                self::fail('Malformed emote packet was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        foreach ([
            static fn () => new EmotePacket(UnsignedLong::fromInt(0), '', -1, '', ''),
            static fn () => new EmotePacket(UnsignedLong::fromInt(0), '', 0x100000000, '', ''),
            static fn () => new EmotePacket(UnsignedLong::fromInt(0), '', 0, '', '', [EmoteFlag::ServerSide, EmoteFlag::ServerSide]),
        ] as $createInvalid) {
            try {
                $createInvalid();
                self::fail('Invalid emote value was constructed.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLevelEventRangesAndFinitePositionFailClosed(): void
    {
        $nonFinite = "\0" . pack('g', INF) . pack('g', 0.0) . pack('g', 0.0) . "\0";
        try {
            LevelEventPacket::decode($nonFinite);
            self::fail('Non-finite level-event position was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }
        foreach ([
            static fn () => new LevelEventPacket(0x80000000, new LevelEventPosition(0.0, 0.0, 0.0), 0),
            static fn () => new LevelEventPacket(0, new LevelEventPosition(0.0, 0.0, 0.0), -0x80000001),
            static fn () => new LevelEventPosition(NAN, 0.0, 0.0),
        ] as $createInvalid) {
            try {
                $createInvalid();
                self::fail('Invalid level-event value was constructed.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testUpdateBlockRangesAndFlagBitsFailClosed(): void
    {
        try {
            UpdateBlockPacket::decode("\0\0\0\0\x20\0");
            self::fail('Unknown update-block flag bit was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }

        foreach ([
            static fn () => new UpdateBlockPacket(new BlockPosition(0, 0, 0), -0x80000001, [], 0),
            static fn () => new UpdateBlockPacket(new BlockPosition(0, 0, 0), 0x100000000, [], 0),
            static fn () => new UpdateBlockPacket(new BlockPosition(0, 0, 0), 0, [UpdateBlockFlag::Network, UpdateBlockFlag::Network], 0),
            static fn () => new UpdateBlockPacket(new BlockPosition(0, 0, 0), 0, [], -1),
            static fn () => new UpdateBlockPacket(new BlockPosition(0, 0, 0), 0, [], 0x100000000),
        ] as $createInvalid) {
            try {
                $createInvalid();
                self::fail('Invalid update-block value was constructed.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function decodeByVector(string $hex, string $wire): void
    {
        match ($hex) {
            self::EMOTE_VECTOR => EmotePacket::decode($wire),
            self::LEVEL_EVENT_VECTOR => LevelEventPacket::decode($wire),
            self::UPDATE_BLOCK_VECTOR => UpdateBlockPacket::decode($wire),
            default => throw new InvalidValueException('Unknown test vector.'),
        };
    }
}
