<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\UnsupportedPacketException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\ProtocolVersion;
use PHPUnit\Framework\TestCase;

final class BedrockPacketRegistryTest extends TestCase
{
    public function testDecodeSupportCanBeQueriedWithoutParsingPayloads(): void
    {
        self::assertTrue(BedrockPacketCodec::supportsDecoding(PacketIds::LOGIN));
        self::assertTrue(BedrockPacketCodec::supportsDecoding(PacketIds::PLAYER_AUTH_INPUT));
        self::assertFalse(BedrockPacketCodec::supportsDecoding(PacketIds::START_GAME));
        self::assertFalse(BedrockPacketCodec::supportsDecoding(0x3ff));
        self::assertFalse(BedrockPacketCodec::supportsDecoding(-1));
        self::assertFalse(BedrockPacketCodec::supportsDecoding(
            PacketIds::LOGIN,
            ProtocolVersion::CURRENT + 1,
        ));
    }

    public function testUnsupportedPacketIsNotCategorizedAsMalformedWireData(): void
    {
        try {
            BedrockPacketCodec::decode(0x3ff, "\xff");
            self::fail('Unsupported packet ID was decoded.');
        } catch (UnsupportedPacketException $failure) {
            self::assertSame(0x3ff, $failure->packetId);
            self::assertSame(
                'Packet ID is not registered for decoding in the Bedrock codec.',
                $failure->getMessage(),
            );
        }
    }
}
