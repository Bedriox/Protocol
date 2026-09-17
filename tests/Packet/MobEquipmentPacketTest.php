<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\MobEquipmentPacket;
use Bedriox\Protocol\Value\UnsignedLong;

final class MobEquipmentPacketTest extends TestCase
{
    private const string EMPTY_RETAIL_VECTOR = '070000000000000000000000';
    private const string POPULATED_VECTOR = 'ac02050002000301030902aabb0807ff';

    public function testRetailEmptyDescriptorVectorUsesZeroStackSize(): void
    {
        $wire = hex2bin(self::EMPTY_RETAIL_VECTOR);
        self::assertIsString($wire);

        $packet = MobEquipmentPacket::decode($wire);

        self::assertEquals(InventoryItemStack::empty(), $packet->item);
        self::assertSame(self::EMPTY_RETAIL_VECTOR, bin2hex($packet->encode()));
    }

    public function testSyntheticPopulatedDescriptorVectorDecodesExactly(): void
    {
        $wire = hex2bin(self::POPULATED_VECTOR);
        self::assertIsString($wire);

        $packet = MobEquipmentPacket::decode($wire);

        self::assertTrue($packet->runtimeEntityId->equals(UnsignedLong::fromInt(300)));
        self::assertEquals(new InventoryItemStack(5, 2, 3, -2, 9, "\xaa\xbb"), $packet->item);
        self::assertSame(8, $packet->inventorySlot);
        self::assertSame(7, $packet->hotbarSlot);
        self::assertSame(255, $packet->windowId);
        self::assertSame(self::POPULATED_VECTOR, bin2hex($packet->encode()));
    }

    public function testEveryTruncationAndTrailingByteFailClosed(): void
    {
        $wire = hex2bin(self::POPULATED_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                MobEquipmentPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated MobEquipment payload was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        MobEquipmentPacket::decode($wire . "\0");
    }

    public function testMalformedDescriptorFieldsFailClosed(): void
    {
        foreach ([
            "\7\0\0\0\0\x80\x80\x02", // aux 32768
            "\7\0\0\0\0\0\2", // network-ID presence is not boolean
            "\7\0\0\0\0\0\0\0\x81\xa0\x06", // user data exceeds 100 KiB
        ] as $wire) {
            try {
                MobEquipmentPacket::decode($wire);
                self::fail('Malformed MobEquipment descriptor was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
