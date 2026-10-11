<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\EntityPickRequestPacket;
use Bedriox\Protocol\Packet\PacketIds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EntityPickRequestPacketTest extends TestCase
{
    private const string KNOWN_WIRE = "\x08\x07\x06\x05\x04\x03\x02\x01\xff\x01";

    public function testKnownVectorAndRegistryRoundTrip(): void
    {
        $packet = new EntityPickRequestPacket(0x0102030405060708, 0xff, true);

        self::assertSame(35, PacketIds::ENTITY_PICK_REQUEST);
        self::assertSame(PacketIds::ENTITY_PICK_REQUEST, $packet->packetId());
        self::assertSame(self::KNOWN_WIRE, BedrockPacketCodec::encode($packet));
        self::assertEquals(
            $packet,
            BedrockPacketCodec::decode(PacketIds::ENTITY_PICK_REQUEST, self::KNOWN_WIRE),
        );
    }

    #[DataProvider('boundaries')]
    public function testPreservesSignedEntityIdAndHotbarBoundaries(int $runtimeEntityId, int $hotbarSlot): void
    {
        $packet = new EntityPickRequestPacket($runtimeEntityId, $hotbarSlot, false);

        self::assertEquals(
            $packet,
            EntityPickRequestPacket::decode($packet->encode()),
        );
    }

    /** @return iterable<string, array{int, int}> */
    public static function boundaries(): iterable
    {
        yield 'minimums' => [PHP_INT_MIN, 0];
        yield 'maximums' => [PHP_INT_MAX, 0xff];
        yield 'negative entity ID' => [-1, 1];
    }

    #[DataProvider('truncations')]
    public function testRejectsEveryTruncation(string $payload): void
    {
        $this->expectException(BufferUnderflowException::class);
        EntityPickRequestPacket::decode($payload);
    }

    /** @return iterable<string, array{string}> */
    public static function truncations(): iterable
    {
        for ($length = 0; $length < strlen(self::KNOWN_WIRE); ++$length) {
            yield 'length ' . $length => [substr(self::KNOWN_WIRE, 0, $length)];
        }
    }

    public function testRejectsInvalidBoolean(): void
    {
        $this->expectException(MalformedDataException::class);
        EntityPickRequestPacket::decode(substr(self::KNOWN_WIRE, 0, -1) . "\x02");
    }

    public function testRejectsTrailingData(): void
    {
        $this->expectException(MalformedDataException::class);
        EntityPickRequestPacket::decode(self::KNOWN_WIRE . "\x00");
    }

    #[DataProvider('invalidHotbarSlots')]
    public function testRejectsOutOfRangeHotbarSlot(int $hotbarSlot): void
    {
        $this->expectException(InvalidValueException::class);
        new EntityPickRequestPacket(0, $hotbarSlot, false);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidHotbarSlots(): iterable
    {
        yield 'below byte' => [-1];
        yield 'above byte' => [0x100];
    }
}
