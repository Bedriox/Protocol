<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Packet\BedrockPacketCodec;
use Bedriox\Protocol\Packet\HandSlot;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\InventoryAction;
use Bedriox\Protocol\Packet\InventoryLegacySlot;
use Bedriox\Protocol\Packet\InventorySource;
use Bedriox\Protocol\Packet\InventorySourceFlag;
use Bedriox\Protocol\Packet\InventorySourceType;
use Bedriox\Protocol\Packet\InventoryTransactionPacket;
use Bedriox\Protocol\Packet\InventoryVector3;
use Bedriox\Protocol\Packet\ItemReleaseActionType;
use Bedriox\Protocol\Packet\ItemReleaseInventoryTransaction;
use Bedriox\Protocol\Packet\ItemUseActionType;
use Bedriox\Protocol\Packet\ItemUseClientCooldownState;
use Bedriox\Protocol\Packet\ItemUseInventoryTransaction;
use Bedriox\Protocol\Packet\ItemUseOnEntityActionType;
use Bedriox\Protocol\Packet\ItemUseOnEntityInventoryTransaction;
use Bedriox\Protocol\Packet\ItemUsePredictedResult;
use Bedriox\Protocol\Packet\ItemUseTriggerType;
use Bedriox\Protocol\Packet\PacketIds;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\BasicInventoryTransaction;
use Bedriox\Protocol\Packet\InventoryTransactionType;
use Bedriox\Protocol\Value\UnsignedLong;

final class InventoryTransactionPacketTest extends TestCase
{
    private const string NORMAL_VECTOR = '00000000';
    private const string ITEM_USE_VECTOR = '000002000001027e0301000000000000000000000000803f00000040000040400000003f0000803e000000bf110100';

    public function testSyntheticLayoutGoldenVectorsDecodeAndReencodeExactly(): void
    {
        $normal = hex2bin(self::NORMAL_VECTOR);
        $itemUse = hex2bin(self::ITEM_USE_VECTOR);
        self::assertIsString($normal);
        self::assertIsString($itemUse);

        self::assertSame(self::NORMAL_VECTOR, bin2hex(InventoryTransactionPacket::decode($normal)->encode()));
        $decoded = InventoryTransactionPacket::decode($itemUse);
        self::assertInstanceOf(ItemUseInventoryTransaction::class, $decoded->transaction);
        self::assertSame(ItemUseActionType::Place, $decoded->transaction->action);
        self::assertSame(ItemUseTriggerType::PlayerInput, $decoded->transaction->trigger);
        self::assertEquals(new BlockPosition(1, 63, -2), $decoded->transaction->blockPosition);
        self::assertSame(17, $decoded->transaction->targetBlockRuntimeId);
        self::assertSame(self::ITEM_USE_VECTOR, bin2hex($decoded->encode()));
    }

    public function testEveryTruncationAndTrailingByteFailClosed(): void
    {
        $wire = hex2bin(self::ITEM_USE_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                InventoryTransactionPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated inventory transaction was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectException(CodecException::class);
        InventoryTransactionPacket::decode($wire . "\0");
    }

    public function testEnumsCountsPresenceAndItemBoundsAreRejected(): void
    {
        foreach ([
            "\0\0\5\0", // unknown transaction type
            "\0\0\0\x65", // 101 actions
            "\0\1", // legacy presence forbidden for request ID zero
            "\3\1", // request ID -2 requires legacy presence
            "\0\0\2\0\0\3", // unknown trigger
            "\0\0\2\0\x08", // unknown item-use action
            "\0\0\4\0\x04", // unknown item-release action
            "\0\0\0\1\x04", // unknown inventory source
            "\0\0\0\1\xff\xff\xff\xff\x0f", // invalid-source sentinel is not an admitted source
            "\0\0\0\1\x02\0\1\x03", // unknown world-interaction source flag
            "\0\0\2\0\0\0\0\0\0\0\0\2", // unknown hand
            "\0\0\2\0\0\0\0\0\0\0\0\0\0\0\x80\x80\2", // aux 32768
        ] as $wire) {
            try {
                InventoryTransactionPacket::decode($wire);
                self::fail('Malformed inventory transaction was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $golden = hex2bin(self::ITEM_USE_VECTOR);
        self::assertIsString($golden);
        foreach ([
            substr($golden, 0, 45) . "\2" . substr($golden, 46), // unknown predicted result
            substr($golden, 0, 46) . "\2", // unknown cooldown state
        ] as $wire) {
            try {
                InventoryTransactionPacket::decode($wire);
                self::fail('Unknown inventory transaction enum was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        $oversizedLegacy = "\3\1\x81\x08";
        $oversizedUserData = str_repeat("\0", 12) . str_repeat("\0", 7) . "\x81\xa0\x06";
        foreach ([$oversizedLegacy, $oversizedUserData] as $wire) {
            try {
                InventoryTransactionPacket::decode($wire);
                self::fail('Oversized inventory transaction was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAllTypedPayloadsRoundTripAndPacketIdIsRegistered(): void
    {
        $release = new InventoryTransactionPacket(0, [], [], new ItemReleaseInventoryTransaction(
            ItemReleaseActionType::Consume,
            2,
            new InventoryItemStack(5, 1, 0, -7, 12, "\x01\x02"),
            new InventoryVector3(1.0, 2.0, 3.0),
        ));
        $decoded = BedrockPacketCodec::decode(PacketIds::INVENTORY_TRANSACTION, $release->encode());
        self::assertEquals($release, $decoded);
        self::assertSame(PacketIds::INVENTORY_TRANSACTION, BedrockPacketCodec::packetId($release));

        $use = new InventoryTransactionPacket(0, [], [], new ItemUseInventoryTransaction(
            ItemUseActionType::UseAsAttack,
            ItemUseTriggerType::SimulationTick,
            new BlockPosition(0, 0, 0),
            255,
            -1,
            HandSlot::Offhand,
            InventoryItemStack::empty(),
            new InventoryVector3(0.0, 0.0, 0.0),
            new InventoryVector3(0.0, 0.0, 0.0),
            0,
            ItemUsePredictedResult::Failure,
            ItemUseClientCooldownState::On,
        ));
        self::assertEquals($use, InventoryTransactionPacket::decode($use->encode()));

        $entity = new InventoryTransactionPacket(0, [], [], new ItemUseOnEntityInventoryTransaction(
            UnsignedLong::fromInt(77),
            ItemUseOnEntityActionType::Attack,
            0,
            InventoryItemStack::empty(),
            new InventoryVector3(4.0, 5.0, 6.0),
            new InventoryVector3(0.0, 1.0, 0.0),
        ));
        self::assertEquals($entity, InventoryTransactionPacket::decode($entity->encode()));

        $sources = [
            new InventorySource(InventorySourceType::Container, -2),
            new InventorySource(InventorySourceType::Global),
            new InventorySource(InventorySourceType::WorldInteraction, flag: InventorySourceFlag::DropItem),
            new InventorySource(InventorySourceType::Creative),
            new InventorySource(InventorySourceType::UntrackedInteractionUi),
            new InventorySource(InventorySourceType::NonImplementedTodo, 127),
        ];
        $actions = [];
        foreach ($sources as $slot => $source) {
            $actions[] = new InventoryAction(
                $source,
                $slot,
                new InventoryItemStack(7, 2, 3, 11, 13, "\xaa"),
                InventoryItemStack::empty(),
            );
        }
        $normal = new InventoryTransactionPacket(
            -2,
            [new InventoryLegacySlot(4, "\x01\x02")],
            $actions,
            new BasicInventoryTransaction(InventoryTransactionType::InventoryMismatch),
        );
        self::assertEquals($normal, InventoryTransactionPacket::decode($normal->encode()));

        $boundaryItem = new InventoryItemStack(
            -0x8000,
            0xffff,
            0x7fff,
            0x7fffffff,
            0xffffffff,
            str_repeat("\xa5", InventoryItemStack::MAXIMUM_USER_DATA_BYTES),
        );
        $boundary = new InventoryTransactionPacket(0, [], [new InventoryAction(
            new InventorySource(InventorySourceType::WorldInteraction, flag: InventorySourceFlag::None),
            0xffffffff,
            $boundaryItem,
            new InventoryItemStack(0x7fff, 0, 0, -0x80000000, 0, ''),
        )], new BasicInventoryTransaction(InventoryTransactionType::Normal));
        self::assertEquals($boundary, InventoryTransactionPacket::decode($boundary->encode()));

        $maximumActions = new InventoryTransactionPacket(0, [], array_fill(0, 100, new InventoryAction(
            new InventorySource(InventorySourceType::Global),
            0,
            InventoryItemStack::empty(),
            InventoryItemStack::empty(),
        )), new BasicInventoryTransaction(InventoryTransactionType::Normal));
        self::assertEquals($maximumActions, InventoryTransactionPacket::decode($maximumActions->encode()));
    }

    public function testPacketConstructorRejectsUntypedArrayElements(): void
    {
        $reflection = new \ReflectionClass(InventoryTransactionPacket::class);
        $transaction = new BasicInventoryTransaction(InventoryTransactionType::Normal);
        foreach ([
            [0, ['not-a-legacy-slot'], [], $transaction],
            [0, [], ['not-an-action'], $transaction],
        ] as $arguments) {
            try {
                $reflection->newInstanceArgs($arguments);
                self::fail('Untyped inventory transaction list element was accepted.');
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
