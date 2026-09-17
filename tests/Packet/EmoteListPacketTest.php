<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\EmoteListPacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Value\UnsignedLong;

final class EmoteListPacketTest extends TestCase
{
    private const string UUID_A = '00112233-4455-6677-8899-aabbccddeeff';
    private const string UUID_B = 'fedcba98-7654-3210-0123-456789abcdef';

    public function testKnownVectorRoundTripsAndRejectsEveryTruncation(): void
    {
        $packet = new EmoteListPacket(UnsignedLong::fromInt(300), [self::UUID_A, self::UUID_B]);
        $wire = hex2bin('ac02027766554433221100ffeeddccbbaa99881032547698badcfeefcdab8967452301');
        self::assertIsString($wire);
        self::assertSame(PacketIds::EMOTE_LIST, $packet->packetId());
        self::assertSame($wire, BedrockPacketCodec::encode($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::EMOTE_LIST, $wire));

        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode(PacketIds::EMOTE_LIST, substr($wire, 0, $length));
                self::fail("Truncation at {$length} was accepted.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEmptyAndMaximumListsAreAccepted(): void
    {
        $empty = new EmoteListPacket(UnsignedLong::fromInt(0), []);
        self::assertSame("\0\0", $empty->encode());

        $ids = array_fill(0, EmoteListPacket::MAX_EMOTE_IDS, self::UUID_A);
        $maximum = new EmoteListPacket(new UnsignedLong(0xffffffff, 0xffffffff), $ids);
        $decoded = EmoteListPacket::decode($maximum->encode());
        self::assertSame(EmoteListPacket::MAX_EMOTE_IDS, count($decoded->emoteIds));
        self::assertTrue($decoded->runtimeEntityId->equals($maximum->runtimeEntityId));
    }

    public function testConstructorRejectsTooManyIds(): void
    {
        $this->expectException(InvalidValueException::class);
        new EmoteListPacket(
            UnsignedLong::fromInt(0),
            array_fill(0, EmoteListPacket::MAX_EMOTE_IDS + 1, self::UUID_A),
        );
    }

    public function testDecoderRejectsTooManyIdsBeforeAllocation(): void
    {
        $this->expectException(MalformedDataException::class);
        EmoteListPacket::decode("\0\x81\x20");
    }

    public function testConstructorRejectsNonListAndInvalidUuid(): void
    {
        try {
            (new \ReflectionClass(EmoteListPacket::class))->newInstanceArgs([
                UnsignedLong::fromInt(0),
                ['id' => self::UUID_A],
            ]);
            self::fail('Associative emote IDs were accepted.');
        } catch (InvalidValueException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidValueException::class);
        new EmoteListPacket(UnsignedLong::fromInt(0), ['not-a-uuid']);
    }

    public function testDecoderRejectsTrailingData(): void
    {
        $this->expectException(MalformedDataException::class);
        EmoteListPacket::decode("\0\0\0");
    }
}
