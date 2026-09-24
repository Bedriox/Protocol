<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\InventoryContainerId;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\MobArmorEquipmentPacket;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class MobArmorEquipmentPacketTest extends TestCase
{
    private const string EMPTY_VECTOR = '0700000000000000000000000000000000000000000000000000000000000000000000000000000000';
    private const string POPULATED_VECTOR = 'ac020100010000011600000200010003011800000300010004011a00000400010005011c00000500010006011e0000';

    public function testEmptyArmorVectorAndRegistryAreExact(): void
    {
        $packet = new MobArmorEquipmentPacket(UnsignedLong::fromInt(7));

        self::assertSame(PacketIds::MOB_ARMOR_EQUIPMENT, $packet->packetId());
        self::assertSame(PacketIds::MOB_ARMOR_EQUIPMENT, BedrockPacketCodec::packetId($packet));
        self::assertSame(self::EMPTY_VECTOR, bin2hex(BedrockPacketCodec::encode($packet)));
        self::assertEquals($packet, BedrockPacketCodec::decode(
            PacketIds::MOB_ARMOR_EQUIPMENT,
            hex2bin(self::EMPTY_VECTOR) ?: '',
        ));
    }

    public function testFiveArmorDescriptorsRetainTheirWireOrder(): void
    {
        $items = [
            new InventoryItemStack(1, 1, 0, 11, 0, ''),
            new InventoryItemStack(2, 1, 3, 12, 0, ''),
            new InventoryItemStack(3, 1, 4, 13, 0, ''),
            new InventoryItemStack(4, 1, 5, 14, 0, ''),
            new InventoryItemStack(5, 1, 6, 15, 0, ''),
        ];
        $packet = new MobArmorEquipmentPacket(UnsignedLong::fromInt(300), ...$items);

        self::assertSame(self::POPULATED_VECTOR, bin2hex($packet->encode()));
        $decoded = MobArmorEquipmentPacket::decode($packet->encode());
        self::assertEquals($items[0], $decoded->helmet);
        self::assertEquals($items[1], $decoded->chestplate);
        self::assertEquals($items[2], $decoded->leggings);
        self::assertEquals($items[3], $decoded->boots);
        self::assertEquals($items[4], $decoded->body);
    }

    public function testEveryTruncationAndTrailingByteFailClosed(): void
    {
        $wire = hex2bin(self::POPULATED_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                MobArmorEquipmentPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated MobArmorEquipment payload was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(CodecException::class);
        MobArmorEquipmentPacket::decode($wire . "\0");
    }

    public function testMalformedDescriptorInsideArmorSnapshotFailsClosed(): void
    {
        $wire = hex2bin(self::EMPTY_VECTOR);
        self::assertIsString($wire);

        $this->expectException(CodecException::class);
        MobArmorEquipmentPacket::decode(substr($wire, 0, 6) . "\2" . substr($wire, 7));
    }

    public function testCurrentPlayerInventoryWindowIdsAreNamed(): void
    {
        self::assertSame(0, InventoryContainerId::INVENTORY);
        self::assertSame(119, InventoryContainerId::OFFHAND);
        self::assertSame(120, InventoryContainerId::ARMOR);
        self::assertSame(122, InventoryContainerId::HOTBAR);
        self::assertSame(123, InventoryContainerId::FIXED_INVENTORY);
        self::assertSame(124, InventoryContainerId::UI);
        self::assertSame(125, InventoryContainerId::REGISTRY);
    }
}
