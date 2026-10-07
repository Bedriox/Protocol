<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\BlockActorDataPacket;
use Bedriox\Protocol\Packet\BlockEventPacket;
use Bedriox\Protocol\Packet\BlockEventType;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\ContainerClosePacket;
use Bedriox\Protocol\Packet\ContainerRegistryCleanupPacket;
use Bedriox\Protocol\Packet\ContainerSlotType;
use Bedriox\Protocol\Packet\ContainerType;
use Bedriox\Protocol\Packet\FullContainerName;
use Bedriox\Protocol\Packet\HorseEquipmentNbt;
use Bedriox\Protocol\Packet\HorseEquipmentSlot;
use Bedriox\Protocol\Packet\NetworkNbtCompound;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\UpdateEquipPacket;
use PHPUnit\Framework\TestCase;

final class StorageContainerPacketTest extends TestCase
{
    private const string EMPTY_NETWORK_NBT = "\x0a\x00\x00";

    public function testBlockEventUsesTheCurrentLiteralContainerStateVector(): void
    {
        $packet = BlockEventPacket::containerState(new BlockPosition(1, -64, 2), true);

        self::assertSame(BlockEventType::ChangeState, $packet->eventType);
        self::assertSame(1, $packet->eventData);
        self::assertSame('027f040202', bin2hex($packet->encode()));
        self::assertSame(PacketIds::BLOCK_EVENT, BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::BLOCK_EVENT, $packet->encode()));
    }

    public function testHorseEquipmentDeclarationUsesTheCurrentPacketLayout(): void
    {
        $packet = new UpdateEquipPacket(7, ContainerType::Horse, 15, 123, self::EMPTY_NETWORK_NBT);

        self::assertSame('070c1ef6010a0000', bin2hex($packet->encode()));
        self::assertSame(PacketIds::UPDATE_EQUIP, BedrockPacketCodec::packetId($packet));
        self::assertEquals(
            $packet,
            BedrockPacketCodec::decode(PacketIds::UPDATE_EQUIP, $packet->encode()),
        );

        foreach ([
            '',
            "\x07",
            "\x07\x0c",
            "\x07\x0c\x1e",
            "\x07\x0c\x1e\xf6",
            "\x07\x00\x1e\xf6\x01\x0a\x00\x00",
            "\x07\x0c\x1e\xf6\x01\x00",
        ] as $malformed) {
            try {
                UpdateEquipPacket::decode($malformed);
                self::fail('Malformed update-equipment payload was accepted.');
            } catch (CodecException) {
            }
        }
    }

    public function testHorseEquipmentNbtRetainsTypedAcceptedAndEquippedItems(): void
    {
        $nbt = HorseEquipmentNbt::encode([
            new HorseEquipmentSlot(0, ['minecraft:saddle'], 'minecraft:saddle'),
            new HorseEquipmentSlot(1, ['minecraft:red_carpet', 'minecraft:blue_carpet']),
        ]);

        NetworkNbtCompound::validate($nbt);
        self::assertSame(
            '0a000905736c6f74730a04030a736c6f744e756d62657200090d61636365707465644974656d730a020a08736c6f744974656d0203417578ff7f08044e616d65106d696e6563726166743a736164646c6500000a046974656d08044e616d65106d696e6563726166743a736164646c650203417578ff7f0000030a736c6f744e756d62657202090d61636365707465644974656d730a040a08736c6f744974656d0203417578ff7f08044e616d65146d696e6563726166743a7265645f63617270657400000a08736c6f744974656d0203417578ff7f08044e616d65156d696e6563726166743a626c75655f63617270657400000000',
            bin2hex($nbt),
        );
        self::assertStringContainsString('slots', $nbt);
        self::assertStringContainsString('acceptedItems', $nbt);
        self::assertStringContainsString('minecraft:saddle', $nbt);
        self::assertStringContainsString('minecraft:red_carpet', $nbt);
        self::assertSame($nbt, (new UpdateEquipPacket(9, ContainerType::Horse, 2, 99, $nbt))->networkNbt);
    }

    public function testHorseEquipmentConversationRejectsEveryTruncationAndTrailingByte(): void
    {
        $packet = new UpdateEquipPacket(
            7,
            ContainerType::Horse,
            17,
            123,
            HorseEquipmentNbt::encode([
                new HorseEquipmentSlot(0, ['minecraft:saddle'], 'minecraft:saddle'),
                new HorseEquipmentSlot(1, ['minecraft:iron_horse_armor']),
            ]),
        );
        $wire = $packet->encode();
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                BedrockPacketCodec::decode(PacketIds::UPDATE_EQUIP, substr($wire, 0, $length));
                self::fail("Truncated update-equipment packet was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
        try {
            BedrockPacketCodec::decode(PacketIds::UPDATE_EQUIP, $wire . "\0");
            self::fail('Update-equipment packet with trailing data was accepted.');
        } catch (CodecException) {
            self::addToAssertionCount(1);
        }
    }

    public function testHorseEquipmentNbtRejectsDuplicateSlotsItemsAndUnacceptedEquipment(): void
    {
        foreach ([
            static fn(): string => HorseEquipmentNbt::encode([
                new HorseEquipmentSlot(0, ['minecraft:saddle']),
                new HorseEquipmentSlot(0, ['minecraft:saddle']),
            ]),
            static fn(): HorseEquipmentSlot => new HorseEquipmentSlot(
                0,
                ['minecraft:saddle', 'minecraft:saddle'],
            ),
            static fn(): HorseEquipmentSlot => new HorseEquipmentSlot(
                0,
                ['minecraft:saddle'],
                'minecraft:diamond_horse_armor',
            ),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid horse-equipment declaration was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testBlockActorDataUsesPositionThenOneNetworkNbtCompound(): void
    {
        $packet = new BlockActorDataPacket(new BlockPosition(1, -64, 2), self::EMPTY_NETWORK_NBT);

        self::assertSame('027f040a0000', bin2hex($packet->encode()));
        self::assertSame(PacketIds::BLOCK_ACTOR_DATA, BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode(PacketIds::BLOCK_ACTOR_DATA, $packet->encode()));
    }

    public function testLittleEndianBlockActorDataIsConvertedBeforeProjection(): void
    {
        $packet = BlockActorDataPacket::fromLittleEndianNbt(
            new BlockPosition(0, 64, 0),
            hex2bin('0a0000030100782a00000000') ?: '',
        );

        self::assertSame('0a000301785400', bin2hex($packet->networkNbt));
        self::assertEquals($packet, BlockActorDataPacket::decode($packet->encode()));
    }

    public function testContainerRegistryCleanupRetainsDynamicAndStorageNames(): void
    {
        $packet = new ContainerRegistryCleanupPacket([
            new FullContainerName(ContainerSlotType::DynamicContainer, 0x12345678),
            new FullContainerName(ContainerSlotType::Barrel),
        ]);

        self::assertSame('023f01785634123a00', bin2hex($packet->encode()));
        self::assertSame(PacketIds::CONTAINER_REGISTRY_CLEANUP, BedrockPacketCodec::packetId($packet));
        self::assertEquals(
            $packet,
            BedrockPacketCodec::decode(PacketIds::CONTAINER_REGISTRY_CLEANUP, $packet->encode()),
        );
    }

    public function testClosePacketUsesTheClosedContainerTypeDomain(): void
    {
        $close = new ContainerClosePacket(5, ContainerType::Container, true);

        self::assertSame('050001', bin2hex($close->encode()));
        self::assertSame(ContainerType::Container, ContainerClosePacket::decode($close->encode())->containerType);
        self::assertSame('fff700', bin2hex(new ContainerClosePacket(255, ContainerType::None, false)->encode()));
    }

    public function testCurrentContainerSlotTypeDomainIsCompleteAndNamed(): void
    {
        self::assertCount(67, ContainerSlotType::cases());
        foreach (range(0, FullContainerName::MAXIMUM_CONTAINER_NAME_ID) as $id) {
            self::assertNotNull(ContainerSlotType::tryFrom($id));
        }
        self::assertSame(ContainerSlotType::LevelEntity, (new FullContainerName(FullContainerName::LEVEL_ENTITY))->slotType());
        self::assertSame(
            ContainerSlotType::HorseEquipment,
            (new FullContainerName(FullContainerName::HORSE_EQUIPMENT))->slotType(),
        );
        self::assertSame(ContainerSlotType::ShulkerBox, (new FullContainerName(FullContainerName::SHULKER_BOX))->slotType());
        self::assertSame(ContainerSlotType::Barrel, (new FullContainerName(FullContainerName::BARREL))->slotType());
        self::assertSame(ContainerSlotType::DynamicContainer, (new FullContainerName(FullContainerName::DYNAMIC))->slotType());
    }

    public function testStoragePacketsRejectEveryTruncationAndTrailingByte(): void
    {
        foreach ([
            BlockEventPacket::containerState(new BlockPosition(1, -64, 2), true),
            new BlockActorDataPacket(new BlockPosition(1, -64, 2), self::EMPTY_NETWORK_NBT),
            new ContainerRegistryCleanupPacket([new FullContainerName(ContainerSlotType::DynamicContainer, 7)]),
        ] as $packet) {
            $wire = $packet->encode();
            for ($length = 0; $length < strlen($wire); ++$length) {
                try {
                    BedrockPacketCodec::decode($packet->packetId(), substr($wire, 0, $length));
                    self::fail("Truncated storage packet was accepted at {$length} bytes.");
                } catch (CodecException) {
                    self::addToAssertionCount(1);
                }
            }
            try {
                BedrockPacketCodec::decode($packet->packetId(), $wire . "\0");
                self::fail('Storage packet with trailing data was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMalformedStorageValuesFailClosed(): void
    {
        foreach ([
            static fn (): Packet => BlockEventPacket::decode("\0\0\0\4\0"),
            static fn (): Packet => BlockActorDataPacket::decode("\0\0\0\x09\0\0"),
            static fn (): Packet => BlockActorDataPacket::decode("\0\0\0\x0a\0"),
            static fn (): Packet => ContainerClosePacket::decode("\0\x25\0"),
            static fn (): Packet => ContainerRegistryCleanupPacket::decode("\x81\x01"),
        ] as $decode) {
            try {
                $decode();
                self::fail('Malformed storage value was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testNetworkNbtLimitsAreAppliedBeforeTraversal(): void
    {
        foreach ([
            '',
            str_repeat("\0", NetworkNbtCompound::MAXIMUM_BYTES + 1),
            "\x0a\x00\x07\x82\x80\x08",
            "\x0a\x00\x09\x01x\x01\x82\x80\x08",
            "\x0a\x00\x03\x01x\0\x03\x01x\0\0",
        ] as $networkNbt) {
            try {
                NetworkNbtCompound::validate($networkNbt);
                self::fail('Invalid or oversized network NBT was accepted.');
            } catch (InvalidValueException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
