<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\FullContainerName;
use Bedriox\Protocol\Packet\InventoryContentPacket;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\InventorySlotPacket;
use Bedriox\Protocol\Packet\PacketIds;

final class InventoryContentAndSlotPacketTest extends TestCase
{
    private const string ITEM = '0200400000012205026162';
    private const string EMPTY = '0000010000000000';

    public function testNonEmptyInventoryContentHasTheCurrentLiteralWireOrder(): void
    {
        $item = new InventoryItemStack(2, 64, 0, 17, 5, 'ab');
        $packet = new InventoryContentPacket(0, [$item, new InventoryItemStack(0, 1, 0, null, 0, '')]);

        self::assertSame(PacketIds::INVENTORY_CONTENT, BedrockPacketCodec::packetId($packet));
        self::assertSame('0002' . self::ITEM . self::EMPTY . '0000' . self::EMPTY, bin2hex($packet->encode()));
        self::assertEquals([$item, new InventoryItemStack(0, 1, 0, null, 0, '')], $packet->items);
        self::assertEquals($packet, InventoryContentPacket::decode($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::INVENTORY_CONTENT, $packet->encode()));
    }

    public function testSlotCorrectionEncodesOptionalValuesInCurrentOrder(): void
    {
        $item = new InventoryItemStack(2, 64, 0, 17, 5, 'ab');
        $packet = new InventorySlotPacket(
            0xff,
            300,
            $item,
            new FullContainerName(66, 0x12345678),
            new InventoryItemStack(0, 1, 0, null, 0, ''),
        );

        self::assertSame(PacketIds::INVENTORY_SLOT, BedrockPacketCodec::packetId($packet));
        self::assertSame(
            'ff01ac020142017856341201' . self::EMPTY . self::ITEM,
            bin2hex($packet->encode()),
        );
        self::assertEquals($packet, InventorySlotPacket::decode($packet->encode()));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::INVENTORY_SLOT, $packet->encode()));
    }

    public function testSlotCorrectionCanOmitBothOptionalValues(): void
    {
        $packet = new InventorySlotPacket(0, 0, new InventoryItemStack(2, 1, 0, null, 5, ''));

        self::assertSame('000000000200010000000500', bin2hex($packet->encode()));
        self::assertEquals($packet, InventorySlotPacket::decode($packet->encode()));
    }

    public function testEveryInventoryPacketTruncationAndTrailingDataFailsClosed(): void
    {
        $packets = [
            new InventoryContentPacket(0, [new InventoryItemStack(2, 64, 0, 17, 5, 'ab')]),
            new InventorySlotPacket(
                0xff,
                300,
                new InventoryItemStack(2, 64, 0, 17, 5, 'ab'),
                new FullContainerName(66, 0x12345678),
                InventoryContentPacket::emptySlot(),
            ),
        ];
        foreach ($packets as $packet) {
            $wire = $packet->encode();
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    $packet instanceof InventoryContentPacket
                        ? InventoryContentPacket::decode(substr($wire, 0, $length))
                        : InventorySlotPacket::decode(substr($wire, 0, $length));
                    self::fail("Truncated inventory packet was accepted at {$length} bytes.");
                } catch (CodecException) {
                    self::addToAssertionCount(1);
                }
            }
            $this->expectMalformedTrailingData($packet, $wire . "\0");
        }
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function malformedWireValues(): iterable
    {
        yield 'content count above limit' => [static fn () => InventoryContentPacket::decode("\0\x81\x01")];
        yield 'content unknown full-container name' => [static fn () => InventoryContentPacket::decode(
            "\0\0\x43\0\0\0\1\0\0\0\0\0",
        )];
        yield 'slot container above byte range' => [static fn () => InventorySlotPacket::decode("\x80\x02")];
        yield 'slot invalid container-name presence' => [static fn () => InventorySlotPacket::decode("\0\0\2")];
        yield 'slot invalid storage-item presence' => [static fn () => InventorySlotPacket::decode("\0\0\0\2")];
    }

    #[DataProvider('malformedWireValues')]
    public function testMalformedInventoryWireValuesFailClosed(callable $decode): void
    {
        $this->expectException(CodecException::class);
        $decode();
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'too many inventory slots' => [
            static fn () => new InventoryContentPacket(0, array_fill(0, 129, InventoryItemStack::empty())),
        ];
        yield 'container ID above byte range' => [
            static fn () => new InventorySlotPacket(256, 0, InventoryItemStack::empty()),
        ];
        yield 'slot above unsigned integer range' => [
            static fn () => new InventorySlotPacket(0, 0x100000000, InventoryItemStack::empty()),
        ];
        yield 'unknown container name' => [static fn () => new FullContainerName(67)];
        yield 'dynamic ID above unsigned integer range' => [static fn () => new FullContainerName(0, 0x100000000)];
    }

    #[DataProvider('invalidValues')]
    public function testInventoryValuesAreBounded(callable $create): void
    {
        $this->expectException(InvalidValueException::class);
        $create();
    }

    private function expectMalformedTrailingData(InventoryContentPacket|InventorySlotPacket $packet, string $wire): void
    {
        try {
            $packet instanceof InventoryContentPacket
                ? InventoryContentPacket::decode($wire)
                : InventorySlotPacket::decode($wire);
            self::fail('Inventory packet with trailing data was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }
    }
}
