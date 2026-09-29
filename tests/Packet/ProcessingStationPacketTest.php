<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\ClientboundMapItemDataPacket;
use Bedriox\Protocol\Packet\EnchantData;
use Bedriox\Protocol\Packet\EnchantOption;
use Bedriox\Protocol\Packet\FurnaceLayout;
use Bedriox\Protocol\Packet\FurnaceLeftTab;
use Bedriox\Protocol\Packet\FurnaceOptions;
use Bedriox\Protocol\Packet\FurnaceType;
use Bedriox\Protocol\Packet\MapDecoration;
use Bedriox\Protocol\Packet\MapInfoRequestPacket;
use Bedriox\Protocol\Packet\MapPixel;
use Bedriox\Protocol\Packet\MapTrackedObject;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PlayerEnchantOptionsPacket;
use Bedriox\Protocol\Packet\SetPlayerFurnaceOptionsPacket;
use PHPUnit\Framework\TestCase;

final class ProcessingStationPacketTest extends TestCase
{
    public function testEnchantOptionsUseTheCurrentCostAndEnchantWidths(): void
    {
        $packet = new PlayerEnchantOptionsPacket([
            new EnchantOption(30, -1, [new EnchantData(300, 5)], [], [], 'Sharpness', 77),
        ]);
        self::assertSame('011effffffff01ac020500000953686172706e6573734d', bin2hex($packet->encode()));
        self::assertEquals($packet, PlayerEnchantOptionsPacket::decode($packet->encode()));
        self::assertSame(PacketIds::PLAYER_ENCHANT_OPTIONS, BedrockPacketCodec::packetId($packet));
    }

    public function testFurnaceOptionsRoundTripAndRegistry(): void
    {
        $packet = new SetPlayerFurnaceOptionsPacket(
            FurnaceType::BlastFurnace,
            new FurnaceOptions(FurnaceLeftTab::Nature, true, FurnaceLayout::Compact),
        );
        self::assertSame('02040104', bin2hex($packet->encode()));
        self::assertEquals($packet, SetPlayerFurnaceOptionsPacket::decode($packet->encode()));
        self::assertSame(PacketIds::SET_PLAYER_FURNACE_OPTIONS, BedrockPacketCodec::packetId($packet));
    }

    public function testMapRequestCarriesBoundedClientPixels(): void
    {
        $packet = new MapInfoRequestPacket(42, [new MapPixel(0x11223344, 16)]);
        self::assertSame('5401000000443322111000', bin2hex($packet->encode()));
        self::assertEquals($packet, MapInfoRequestPacket::decode($packet->encode()));
        self::assertSame(PacketIds::MAP_INFO_REQUEST, BedrockPacketCodec::packetId($packet));
    }

    public function testClientboundMapDataRoundTrip(): void
    {
        $packet = new ClientboundMapItemDataPacket(
            42,
            0,
            true,
            new BlockPosition(1, 64, -2),
            [7],
            2,
            [MapTrackedObject::entity(7), MapTrackedObject::block(new BlockPosition(2, 65, 3))],
            [new MapDecoration(1, 2, 3, 4, 'home', 0x11223344)],
            2,
            1,
            0,
            0,
            [0x01020304, 0x05060708],
        );
        self::assertEquals($packet, ClientboundMapItemDataPacket::decode($packet->encode()));
        self::assertSame(PacketIds::CLIENTBOUND_MAP_ITEM_DATA, BedrockPacketCodec::packetId($packet));
    }

    public function testEveryPacketRejectsTruncation(): void
    {
        $packets = [
            new PlayerEnchantOptionsPacket([new EnchantOption(1, 0, [], [], [], '', 1)]),
            new SetPlayerFurnaceOptionsPacket(FurnaceType::Furnace, new FurnaceOptions(FurnaceLeftTab::Search, false, FurnaceLayout::Normal)),
            new MapInfoRequestPacket(1, [new MapPixel(0, 0)]),
            new ClientboundMapItemDataPacket(1, 0, false, new BlockPosition(0, 0, 0)),
        ];
        $rejections = 0;
        foreach ($packets as $packet) {
            $wire = $packet->encode();
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    $packet::decode(substr($wire, 0, $length));
                    self::fail($packet::class . ' accepted a truncated payload of length ' . $length);
                } catch (BufferUnderflowException) {
                    ++$rejections;
                }
            }
        }
        self::assertGreaterThan(0, $rejections);
    }
}
