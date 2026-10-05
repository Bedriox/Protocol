<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\ChangeDimensionPacket;
use Bedriox\Protocol\Packet\DimensionId;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PlayerActionPacket;
use Bedriox\Protocol\Packet\PlayerActionType;
use Bedriox\Protocol\Packet\ServerboundLoadingScreenPacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChangeDimensionPacketTest extends TestCase
{
    /** @return iterable<string, array{ChangeDimensionPacket, string}> */
    public static function vectors(): iterable
    {
        yield 'nether without identified loading screen' => [
            new ChangeDimensionPacket(DimensionId::Nether, 1.0, 64.0, -2.0, false),
            '020000803f00008042000000c00000',
        ];
        yield 'end respawn with identified loading screen' => [
            new ChangeDimensionPacket(DimensionId::End, 1.0, 2.0, 3.0, true, 7),
            '040000803f0000004000004040010107000000',
        ];
        yield 'maximum loading screen identifier' => [
            new ChangeDimensionPacket(DimensionId::Overworld, 0.0, 0.0, 0.0, false, 0xffffffff),
            '000000000000000000000000000001ffffffff',
        ];
    }

    #[DataProvider('vectors')]
    public function testLiteralVectorsRegistryAndRoundTrip(ChangeDimensionPacket $packet, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        self::assertSame(PacketIds::CHANGE_DIMENSION, $packet->packetId());
        self::assertSame(PacketIds::CHANGE_DIMENSION, BedrockPacketCodec::packetId($packet));
        self::assertSame($wire, BedrockPacketCodec::encode($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::CHANGE_DIMENSION, $wire));
    }

    #[DataProvider('vectors')]
    public function testEveryTruncationAndTrailingByte(ChangeDimensionPacket $packet, string $hex): void
    {
        $wire = hex2bin($hex);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                ChangeDimensionPacket::decode(substr($wire, 0, $length));
                self::fail("Change-dimension truncation at {$length} bytes was accepted.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(MalformedDataException::class);
        ChangeDimensionPacket::decode($wire . "\0");
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPayloads(): iterable
    {
        yield 'unknown dimension' => ["\x06" . str_repeat("\0", 14)];
        yield 'noncanonical dimension' => ["\x82\x00" . str_repeat("\0", 14)];
        yield 'nonfinite coordinate' => ["\x00\x00\x00\xc0\x7f" . str_repeat("\0", 10)];
        yield 'respawn boolean' => ["\x00" . str_repeat("\0", 12) . "\x02\x00"];
        yield 'loading-screen presence boolean' => ["\x00" . str_repeat("\0", 13) . "\x02"];
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedPayloadsAreRejected(string $wire): void
    {
        $this->expectException(CodecException::class);
        ChangeDimensionPacket::decode($wire);
    }

    public function testConstructorRejectsInvalidCoordinatesAndLoadingScreenIdentifiers(): void
    {
        foreach ([NAN, INF, -INF, 3.5e38] as $coordinate) {
            try {
                new ChangeDimensionPacket(DimensionId::Nether, $coordinate, 0.0, 0.0, false);
                self::fail('Invalid change-dimension coordinate was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
        foreach ([-1, 0x100000000] as $loadingScreenId) {
            try {
                new ChangeDimensionPacket(DimensionId::Nether, 0.0, 0.0, 0.0, false, $loadingScreenId);
                self::fail('Out-of-range loading-screen ID was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testNormalClientReplyConversationIsTypedAndRegistered(): void
    {
        $zero = new BlockPosition(0, 0, 0);
        $dimensionAck = new PlayerActionPacket(
            UnsignedLong::fromInt(1),
            PlayerActionType::DimensionChangeSuccess,
            $zero,
            $zero,
            0,
        );
        $loadingStarted = new ServerboundLoadingScreenPacket(ServerboundLoadingScreenPacket::START, 7);
        $loadingEnded = new ServerboundLoadingScreenPacket(ServerboundLoadingScreenPacket::END, 7);

        foreach ([
            [$dimensionAck, PacketIds::PLAYER_ACTION, '011c00000000000000'],
            [$loadingStarted, PacketIds::SERVERBOUND_LOADING_SCREEN, '020107000000'],
            [$loadingEnded, PacketIds::SERVERBOUND_LOADING_SCREEN, '040107000000'],
        ] as [$packet, $packetId, $hex]) {
            $wire = hex2bin($hex);
            self::assertIsString($wire);
            self::assertSame($packetId, BedrockPacketCodec::packetId($packet));
            self::assertSame($wire, BedrockPacketCodec::encode($packet));
            self::assertEquals($packet, BedrockPacketCodec::decode($packetId, $wire));
        }
    }
}
