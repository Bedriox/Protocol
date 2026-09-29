<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\Ability;
use Bedriox\Protocol\Packet\AbilityLayer;
use Bedriox\Protocol\Packet\ActorMetadata;
use Bedriox\Protocol\Packet\AddItemActorPacket;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\ConsumeItemStackRequestAction;
use Bedriox\Protocol\Packet\CraftCreativeItemStackRequestAction;
use Bedriox\Protocol\Packet\CreateItemStackRequestAction;
use Bedriox\Protocol\Packet\CreativeContentPacket;
use Bedriox\Protocol\Packet\CreativeItemCategory;
use Bedriox\Protocol\Packet\CreativeItemEntry;
use Bedriox\Protocol\Packet\CreativeItemGroup;
use Bedriox\Protocol\Packet\DestroyItemStackRequestAction;
use Bedriox\Protocol\Packet\DropItemStackRequestAction;
use Bedriox\Protocol\Packet\FullContainerName;
use Bedriox\Protocol\Packet\GameType;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\ItemStackRequest;
use Bedriox\Protocol\Packet\ItemStackRequestPacket;
use Bedriox\Protocol\Packet\ItemStackRequestSlot;
use Bedriox\Protocol\Packet\LevelEventPacket;
use Bedriox\Protocol\Packet\LevelEventPosition;
use Bedriox\Protocol\Packet\LevelEventType;
use Bedriox\Protocol\Packet\LevelSoundEventName;
use Bedriox\Protocol\Packet\LevelSoundEventPacket;
use Bedriox\Protocol\Packet\MineBlockItemStackRequestAction;
use Bedriox\Protocol\Packet\MoveActorDeltaPacket;
use Bedriox\Protocol\Packet\Packet;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\PlayerPermission;
use Bedriox\Protocol\Packet\RequestPermissionsPacket;
use Bedriox\Protocol\Packet\RejectedItemStackRequestAction;
use Bedriox\Protocol\Packet\SetPlayerGameTypePacket;
use Bedriox\Protocol\Packet\TakeItemActorPacket;
use Bedriox\Protocol\Packet\UpdatePlayerGameTypePacket;
use Bedriox\Protocol\Packet\StartGamePacket;
use Bedriox\Protocol\Value\UnsignedLong;
use PHPUnit\Framework\TestCase;

final class GameplayMilestonePacketTest extends TestCase
{
    public function testNamedGameTypesAndCurrentGameTypePacketsHaveKnownVectors(): void
    {
        $set = new SetPlayerGameTypePacket(GameType::Spectator);
        self::assertSame('0c', bin2hex($set->encode()));
        self::assertEquals($set, BedrockPacketCodec::decode(PacketIds::SET_PLAYER_GAME_TYPE, $set->encode()));

        $update = new UpdatePlayerGameTypePacket(GameType::Creative, -2, UnsignedLong::fromInt(300));
        self::assertSame('0203ac02', bin2hex($update->encode()));
        self::assertPacketRoundTrips($update);
    }

    public function testLevelEventHelpersExposeCurrentBreakingSemantics(): void
    {
        $position = new LevelEventPosition(1.0, 2.0, 3.0);
        self::assertSame(LevelEventType::StartBlockBreak, LevelEventPacket::startBlockBreak($position, 65535)->type());
        self::assertSame(LevelEventType::StopBlockBreak, LevelEventPacket::stopBlockBreak($position)->type());
        self::assertSame(LevelEventType::UpdateBlockBreak, LevelEventPacket::updateBlockBreak($position, 42)->type());
        self::assertSame(LevelEventType::DestroyBlock, LevelEventPacket::destroyBlock($position, 9)->type());
        self::assertSame(LevelEventType::DestroyBlockWithoutSound, LevelEventPacket::destroyBlock($position, 9, false)->type());
        self::assertSame(LevelEventType::CrackBlock, LevelEventPacket::crackBlock($position, 9)->type());
        self::assertSame(LevelEventType::PunchBlockEast, LevelEventPacket::punchBlock($position, 9, 5)->type());

        $this->expectException(InvalidValueException::class);
        LevelEventPacket::punchBlock($position, 9, 6);
    }

    public function testCreativeContentUsesOneByteGroupsAndTypedEntries(): void
    {
        $item = new InventoryItemStack(5, 2, 3, null, 7, '');
        $packet = new CreativeContentPacket(
            [new CreativeItemGroup(CreativeItemCategory::Nature, 'nature', $item)],
            [new CreativeItemEntry(9, $item, 0)],
        );
        self::assertSame('0102066e61747572650a0200030e0001090a0200030e0000', bin2hex($packet->encode()));
        self::assertPacketRoundTrips($packet);
        $this->assertRejectsEveryTruncation(CreativeContentPacket::decode(...), $packet->encode());
    }

    public function testCreativeContentRejectsInvalidCountsCategoriesAndReferences(): void
    {
        foreach (["\x81\x02", "\1\7"] as $wire) {
            try {
                CreativeContentPacket::decode($wire);
                self::fail('Malformed creative content was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidValueException::class);
        $item = new InventoryItemStack(5, 1, 0, null, 0, '');
        new CreativeContentPacket([new CreativeItemGroup(CreativeItemCategory::Items, '', $item)], [new CreativeItemEntry(1, $item, 1)]);
    }

    public function testCurrentInventoryActionsHaveKnownVectorAndRoundTrip(): void
    {
        $slot = new ItemStackRequestSlot(new FullContainerName(FullContainerName::INVENTORY), 2, 17);
        $packet = new ItemStackRequestPacket([new ItemStackRequest(3, [
            new DropItemStackRequestAction(2, $slot, true),
            new DestroyItemStackRequestAction(1, $slot),
            new ConsumeItemStackRequestAction(1, $slot),
            new CreateItemStackRequestAction(4),
            new MineBlockItemStackRequestAction(1, -2, 17),
            new CraftCreativeItemStackRequestAction(9, 2),
        ])]);
        self::assertSame(
            '0106060303021d000211000000010404011d0002110000000505011d000211000000060604090b0203110000000c0e090200ffffffff',
            bin2hex($packet->encode()),
        );
        $decoded = ItemStackRequestPacket::decode($packet->encode());
        self::assertEquals($packet, $decoded);
        self::assertInstanceOf(RejectedItemStackRequestAction::class, $decoded->requests[0]->actions[0]);
        $this->assertRejectsEveryTruncation(ItemStackRequestPacket::decode(...), $packet->encode());
    }

    public function testCreativeRequestCanCarryBoundedCraftResultsAdvisory(): void
    {
        $wire = hex2bin('0106020c0e09011113000100ffffffff');
        self::assertNotFalse($wire);
        $packet = ItemStackRequestPacket::decode($wire);
        self::assertCount(2, $packet->requests[0]->actions);
        self::assertInstanceOf(\Bedriox\Protocol\Packet\CraftResultsItemStackRequestAction::class, $packet->requests[0]->actions[1]);
        self::assertSame($wire, $packet->encode());
    }

    public function testCreativeActionUsesEnumMarkerDistinctFromWireType(): void
    {
        $wire = hex2bin('0106010c0e090100ffffffff');
        self::assertNotFalse($wire);
        $request = ItemStackRequestPacket::decode($wire);
        self::assertEquals(new CraftCreativeItemStackRequestAction(9, 1), $request->requests[0]->actions[0]);
        self::assertSame($wire, $request->encode());

        $incorrectMarker = hex2bin('0106010c0c090100ffffffff');
        self::assertNotFalse($incorrectMarker);
        $this->expectException(\Bedriox\Protocol\Exception\ItemStackRequestDecodeException::class);
        ItemStackRequestPacket::decode($incorrectMarker);
    }

    public function testUnknownActionReportsOnlyItsBoundedDecodeLocation(): void
    {
        $wire = hex2bin('010001121400ffffffff');
        self::assertNotFalse($wire);
        try {
            ItemStackRequestPacket::decode($wire);
            self::fail('Unsupported action should be rejected.');
        } catch (\Bedriox\Protocol\Exception\ItemStackRequestDecodeException $failure) {
            self::assertSame('action', $failure->stage);
            self::assertSame('unsupported_action_type', $failure->detailCode);
            self::assertSame(3, $failure->byteOffset);
            self::assertSame(0, $failure->actionIndex);
            self::assertSame(18, $failure->actionType);
            self::assertStringNotContainsString(bin2hex($wire), $failure->getMessage());
        }
    }

    public function testCraftResultsWithBlockItemDescriptorHasKnownVector(): void
    {
        $wire = hex2bin('0106011113010101156d696e6563726166743a636f62626c6573746f6e65000100a082d1df0d000100ffffffff');
        self::assertNotFalse($wire);
        $packet = ItemStackRequestPacket::decode($wire);
        $action = $packet->requests[0]->actions[0];
        self::assertInstanceOf(\Bedriox\Protocol\Packet\CraftResultsItemStackRequestAction::class, $action);
        self::assertSame('minecraft:cobblestone', $action->results[0]->descriptorValue);
        self::assertSame(-604_749_536, $action->results[0]->blockRuntimeId);
        self::assertSame($wire, $packet->encode());
        $this->assertRejectsEveryTruncation(ItemStackRequestPacket::decode(...), $wire);

        $oversized = hex2bin('010601111111');
        self::assertNotFalse($oversized);
        $this->expectException(\Bedriox\Protocol\Exception\MalformedDataException::class);
        ItemStackRequestPacket::decode($oversized);
    }

    public function testPermissionsPacketHasKnownVectorAndRejectsUnknownPermission(): void
    {
        $packet = new RequestPermissionsPacket(-2, PlayerPermission::Operator, 0x1234);
        self::assertSame('feffffffffffffff043412', bin2hex($packet->encode()));
        self::assertPacketRoundTrips($packet);
        $this->assertRejectsEveryTruncation(RequestPermissionsPacket::decode(...), $packet->encode());

        $this->expectException(CodecException::class);
        RequestPermissionsPacket::decode("\0\0\0\0\0\0\0\0\x08\0\0");
    }

    public function testDroppedItemActorConversationHasKnownVectors(): void
    {
        $add = new AddItemActorPacket(
            -2,
            UnsignedLong::fromInt(7),
            new InventoryItemStack(5, 2, 3, 11, 7, ''),
            1.0, 2.0, 3.0,
            -1.0, -2.0, -3.0,
            [ActorMetadata::byte(3, 1)],
            true,
        );
        self::assertSame(
            '03070500020003011607000000803f0000004000004040000080bf000000c0000040c0010300000101',
            bin2hex($add->encode()),
        );
        self::assertPacketRoundTrips($add);

        $take = new TakeItemActorPacket(UnsignedLong::fromInt(7), UnsignedLong::fromInt(8));
        self::assertSame('0708', bin2hex($take->encode()));
        self::assertPacketRoundTrips($take);

        $move = new MoveActorDeltaPacket(
            UnsignedLong::fromInt(7),
            1.0, null, 3.0,
            null, 90.0, null,
            true, false, true, false,
            UnsignedLong::fromInt(300),
        );
        self::assertSame('07010000803f0001000040400001400001000100ac02', bin2hex($move->encode()));
        self::assertPacketRoundTrips($move);

        $this->assertRejectsEveryTruncation(AddItemActorPacket::decode(...), $add->encode());
        $this->assertRejectsEveryTruncation(TakeItemActorPacket::decode(...), $take->encode());
        $this->assertRejectsEveryTruncation(MoveActorDeltaPacket::decode(...), $move->encode());
    }

    public function testNewGameplayValuesRejectOutOfRangeConstruction(): void
    {
        $slot = new ItemStackRequestSlot(new FullContainerName(FullContainerName::INVENTORY), 0, 0);
        foreach ([
            static fn () => new SetPlayerGameTypePacket(7),
            static fn () => new DropItemStackRequestAction(65, $slot, false),
            static fn () => new CreateItemStackRequestAction(256),
            static fn () => new CraftCreativeItemStackRequestAction(0, 1),
            static fn () => new RequestPermissionsPacket(0, PlayerPermission::Member, 0x10000),
            static fn () => new AddItemActorPacket(
                1,
                UnsignedLong::fromInt(1),
                InventoryItemStack::empty(),
                0.0, 0.0, 0.0,
                0.0, 0.0, 0.0,
            ),
            static fn () => new MoveActorDeltaPacket(
                UnsignedLong::fromInt(1),
                NAN, null, null,
                null, null, null,
                false, false, false, false,
                UnsignedLong::fromInt(0),
            ),
        ] as $construct) {
            try {
                $construct();
                self::fail('Out-of-range gameplay value was accepted.');
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTypedAbilityMasksPreserveCurrentWireValues(): void
    {
        $layer = AbilityLayer::fromAbilities(
            1,
            Ability::cases(),
            [Ability::Build, Ability::Mine, Ability::VerticalFlySpeed],
            0.05,
            1.0,
            0.1,
        );
        self::assertSame(0x000fffff, $layer->abilitiesSet);
        self::assertSame(0x00080003, $layer->abilityValues);
        self::assertTrue($layer->supports(Ability::NoClip));
        self::assertTrue($layer->enabled(Ability::VerticalFlySpeed));
        self::assertFalse($layer->enabled(Ability::Flying));
        self::assertSame(1 << 19, AbilityLayer::maskOf(Ability::VerticalFlySpeed));

        foreach ([
            static fn () => AbilityLayer::maskOf(Ability::Build, Ability::Build),
            static fn () => AbilityLayer::fromAbilities(1, [Ability::Build], [Ability::Mine], 0.05, 1.0, 0.1),
        ] as $construct) {
            try {
                $construct();
                self::fail('Invalid typed ability mask was accepted.');
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFixedFlatStartGameProjectsTypedPlayerAndLevelGameTypes(): void
    {
        $default = StartGamePacket::fixedFlat(1, UnsignedLong::fromInt(2), 0.5, 65.0, -0.5, 'level', 'Flat');
        self::assertSame(GameType::Survival, $default->playerGameType);
        self::assertSame(GameType::Survival, $default->levelGameType);
        self::assertTrue($default->blockNetworkIdsAreHashes);
        self::assertSame('2dc8ba6efcb2b6ed7ad0215a5ec418cf51c4b56c27e8ee4cd1bd343eccb3fc2b', hash('sha256', $default->encode()));

        $configured = StartGamePacket::fixedFlat(
            1,
            UnsignedLong::fromInt(2),
            0.5,
            65.0,
            -0.5,
            'level',
            'Flat',
            playerGameType: GameType::Creative,
            levelGameType: GameType::Adventure,
        );
        self::assertSame(GameType::Creative, $configured->playerGameType);
        self::assertSame(GameType::Adventure, $configured->levelGameType);

        $reader = ByteBufferReader::fromString($configured->encode(), strlen($configured->encode()));
        $uniqueId = $reader->readSignedVarLong();
        $runtimeId = $uniqueId->reader->readUnsignedVarLong();
        $playerGameType = $runtimeId->reader->readSignedVarInt();
        $reader = $playerGameType->reader;
        for ($index = 0; $index < 5; ++$index) {
            $reader = $reader->readFloatLE()->reader;
        }
        $reader = $reader->readSignedLongLE()->reader->readUnsignedShortLE()->reader;
        $reader = $reader->readString(16)->reader;
        $dimension = $reader->readSignedVarInt();
        $generator = $dimension->reader->readSignedVarInt();
        $levelGameType = $generator->reader->readSignedVarInt();
        self::assertSame(GameType::Creative->value, $playerGameType->value);
        self::assertSame(GameType::Adventure->value, $levelGameType->value);
    }

    public function testLevelSoundEventHasCurrentKnownVectorAndRejectsEveryTruncation(): void
    {
        self::assertSame('hit', LevelSoundEventName::hit()->value);
        self::assertSame('break', LevelSoundEventName::break()->value);
        self::assertSame('place', LevelSoundEventName::place()->value);
        self::assertSame('glass', LevelSoundEventName::glass()->value);
        self::assertSame('potion.brewed', LevelSoundEventName::potionBrewed()->value);
        $packet = new LevelSoundEventPacket(
            LevelSoundEventName::hit(),
            new LevelEventPosition(1.0, 2.0, 3.0),
            -2,
            'minecraft:stone',
            false,
            true,
            -2,
            new LevelEventPosition(-1.0, -2.0, -3.0),
        );
        self::assertSame(
            '036869740000803f0000004000004040030f6d696e6563726166743a73746f6e650001feffffffffffffff01000080bf000000c0000040c0',
            bin2hex($packet->encode()),
        );
        self::assertPacketRoundTrips($packet);
        $this->assertRejectsEveryTruncation(LevelSoundEventPacket::decode(...), $packet->encode());

        foreach ([
            static fn () => new LevelSoundEventName(''),
            static fn () => new LevelSoundEventPacket(LevelSoundEventName::hit(), new LevelEventPosition(0.0, 0.0, 0.0), 0x80000000),
            static fn () => new LevelSoundEventPacket(LevelSoundEventName::hit(), new LevelEventPosition(0.0, 0.0, 0.0), identifier: str_repeat('x', 4_097)),
        ] as $construct) {
            try {
                $construct();
                self::fail('Invalid level sound value was accepted.');
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @param callable(string): Packet $decode */
    private function assertRejectsEveryTruncation(callable $decode, string $wire): void
    {
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                $decode(substr($wire, 0, $length));
                self::fail("Truncated packet was accepted at {$length} bytes.");
            } catch (CodecException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            $decode($wire . "\0");
            self::fail('Packet accepted trailing data.');
        } catch (CodecException) {
            $this->addToAssertionCount(1);
        }
    }

    private static function assertPacketRoundTrips(Packet $packet): void
    {
        self::assertSame($packet->packetId(), BedrockPacketCodec::packetId($packet));
        self::assertEquals($packet, BedrockPacketCodec::decode($packet->packetId(), $packet->encode()));
    }
}
