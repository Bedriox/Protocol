<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\CodecSupport;
use Bedriox\Protocol\Packet\OpenSignPacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\SignBlockActorData;
use Bedriox\Protocol\Packet\SignTextData;
use PHPUnit\Framework\TestCase;

final class SignPacketTest extends TestCase
{
    public function testOpenSignUsesProtocol2193LayoutAndRegistry(): void
    {
        $packet = new OpenSignPacket(new BlockPosition(-1, 64, 2), true);

        self::assertSame(PacketIds::OPEN_SIGN, BedrockPacketCodec::packetId($packet));
        self::assertSame('0180010401', bin2hex($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::OPEN_SIGN, $packet->encode()));
    }

    public function testOpenSignRejectsNonCanonicalBooleanAndTrailingBytes(): void
    {
        foreach ([pack('H*', '0180010402'), pack('H*', '018001040100')] as $payload) {
            try {
                OpenSignPacket::decode($payload);
                self::fail('Malformed open-sign payload was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSignBlockActorRoundTripsTypedBoundedState(): void
    {
        $data = new SignBlockActorData(
            new BlockPosition(4, -12, 9),
            new SignTextData("one\ntwo", -16711936, true, owner: 'player-1'),
            new SignTextData('back', -16777216, false),
            true,
            73,
        );

        $decoded = SignBlockActorData::decode($data->encode());

        self::assertEquals($data, $decoded);
    }

    public function testHangingSignKeepsItsDistinctBlockActorIdentifier(): void
    {
        $data = new SignBlockActorData(
            new BlockPosition(4, 70, 9),
            new SignTextData('front', -16777216, false),
            new SignTextData('back', -16777216, false),
            identifier: 'HangingSign',
        );

        self::assertEquals($data, SignBlockActorData::decode($data->encode()));
    }

    public function testRetailUpdateMayOmitPresentationDefaults(): void
    {
        $encoded = CodecSupport::writer()->writeUnsignedByte(10)->writeString('', 0)
            ->writeUnsignedByte(10)->writeString('BackText', 16)
            ->writeUnsignedByte(8)->writeString('Text', 16)->writeString('back', 1_023)
            ->writeUnsignedByte(0)
            ->writeUnsignedByte(10)->writeString('FrontText', 16)
            ->writeUnsignedByte(8)->writeString('Text', 16)->writeString('front', 1_023)
            ->writeUnsignedByte(0)
            ->writeUnsignedByte(8)->writeString('id', 16)->writeString('Sign', 16)
            ->writeUnsignedByte(3)->writeString('x', 1)->writeSignedVarInt(1)
            ->writeUnsignedByte(3)->writeString('y', 1)->writeSignedVarInt(2)
            ->writeUnsignedByte(3)->writeString('z', 1)->writeSignedVarInt(3)
            ->writeUnsignedByte(0)
            ->toString();

        $decoded = SignBlockActorData::decode($encoded);
        self::assertFalse($decoded->waxed);
        self::assertSame(-16777216, $decoded->front->color);
        self::assertFalse($decoded->front->glowing);
        self::assertTrue($decoded->front->persistFormatting);
    }

    public function testSignBlockActorRejectsWrongKnownTagTypesAndOversizedText(): void
    {
        $valid = new SignBlockActorData(
            new BlockPosition(1, 2, 3),
            new SignTextData('front', -16777216, false),
            new SignTextData('back', -16777216, false),
        );
        $wrongType = str_replace("\x03\x01\x78", "\x08\x01\x78", $valid->encode());

        foreach ([$wrongType, null] as $payload) {
            try {
                if ($payload !== null) {
                    SignBlockActorData::decode($payload);
                } else {
                    new SignTextData(str_repeat('x', SignTextData::MAXIMUM_TEXT_BYTES + 1), 0, false);
                }
                self::fail('Invalid sign block-actor data was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidValueException::class);
        SignBlockActorData::decode(str_repeat("\0", SignBlockActorData::MAXIMUM_BYTES + 1));
    }
}
