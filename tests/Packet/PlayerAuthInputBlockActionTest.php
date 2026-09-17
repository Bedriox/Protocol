<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Packet;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Packet\BlockPosition;
use Bedriox\Protocol\Packet\InventoryAction;
use Bedriox\Protocol\Packet\InventoryItemStack;
use Bedriox\Protocol\Packet\InventorySourceType;
use Bedriox\Protocol\Packet\PlayerActionType;
use Bedriox\Protocol\Packet\PlayerAuthInputFlag;
use Bedriox\Protocol\Packet\PlayerAuthInputPacket;
use Bedriox\Protocol\Packet\PlayerBlockAction;
use Bedriox\Protocol\Packet\PlayerItemUseTransaction;
use Bedriox\Protocol\Value\UnsignedLong;

final class PlayerAuthInputBlockActionTest extends TestCase
{
    private const string GOLDEN_VECTOR = '00000000000000000000803ff43d8342000000c000000000000000000000000001460100000000000000000000070000000000000000000000000000010100027e030200000000000000000000000000000000803f000000000000000000000000';
    private const string ITEM_USE_VECTOR = '000000000000000000000000f43d8342000000000000000000000000000000000144010000000000000000000000000000000000000000000000010000000201027e0301000000000000000000000000000000008042000000000000003f0000003f0000003f0001000000000000000000000000000000000000000000000000000000000000000000';
    private const string ITEM_USE_WITH_ACTION_VECTOR = '000000000000000000000000f43d8342000000000000000000000000000000000144010000000000000000000000000000000000000000000000010000010001fe000205000100000103000000000000000000000201027e0301000000000000000000000000000000008042000000000000003f0000003f0000003f0001000000000000000000000000000000000000000000000000000000000000000000';

    public function testCurrentBlockActionVectorDecodesToTypedBoundedData(): void
    {
        $wire = hex2bin(self::GOLDEN_VECTOR);
        self::assertIsString($wire);

        $packet = PlayerAuthInputPacket::decode($wire);

        self::assertTrue($packet->hasInput(PlayerAuthInputFlag::PerformBlockActions));
        self::assertEquals([
            new PlayerBlockAction(PlayerActionType::StartDestroyBlock, new BlockPosition(1, 63, -2), 1),
        ], $packet->blockActions);
        self::assertSame(self::GOLDEN_VECTOR, bin2hex($packet->encode()));
    }

    public function testEveryTruncationOfBlockActionVectorFailsClosed(): void
    {
        $wire = hex2bin(self::GOLDEN_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                PlayerAuthInputPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated PlayerAuthInput was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testBlockActionCountTypeAndPresenceAreValidated(): void
    {
        $wire = hex2bin(self::GOLDEN_VECTOR);
        self::assertIsString($wire);
        $malformed = [
            substr($wire, 0, 61) . "\x65" . substr($wire, 62), // 101 actions exceeds the bound
            substr($wire, 0, 62) . "\x0a" . substr($wire, 63), // StartSleeping is not valid here
            substr($wire, 0, 60) . "\0" . substr($wire, 61), // flag requires the payload
        ];

        foreach ($malformed as $payload) {
            try {
                PlayerAuthInputPacket::decode($payload);
                self::fail('Malformed PlayerAuthInput block action was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testPresentEmptyBlockActionListIsDistinctFromAbsent(): void
    {
        $packet = new PlayerAuthInputPacket(
            0.0, 0.0, 0.0, 65.621, 0.0, 0.0, 0.0, 0.0,
            [PlayerAuthInputFlag::PerformBlockActions->value], 1, 0, 0,
            0.0, 0.0, UnsignedLong::fromInt(0), 0.0, 0.0, 0.0,
            0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0,
            true, null, [],
        );

        self::assertSame([], PlayerAuthInputPacket::decode($packet->encode())->blockActions);
    }

    public function testCurrentEmptyHandItemUseVectorDecodesToTypedBoundedData(): void
    {
        $wire = hex2bin(self::ITEM_USE_VECTOR);
        self::assertIsString($wire);

        $packet = PlayerAuthInputPacket::decode($wire);

        self::assertTrue($packet->hasInput(PlayerAuthInputFlag::PerformItemInteraction));
        self::assertInstanceOf(PlayerItemUseTransaction::class, $packet->itemUseTransaction);
        self::assertSame(1, $packet->itemUseTransaction->actionType);
        self::assertEquals(new BlockPosition(1, 63, -2), $packet->itemUseTransaction->blockPosition);
        self::assertSame(0, $packet->itemUseTransaction->inventoryActionCount);
        self::assertSame(0, $packet->itemUseTransaction->hand);
        self::assertSame(0, $packet->itemUseTransaction->itemRuntimeId);
        self::assertNull($packet->itemUseTransaction->itemStackNetworkId);
        self::assertSame(0, $packet->itemUseTransaction->itemBlockRuntimeId);
        self::assertSame(0, $packet->itemUseTransaction->targetBlockRuntimeId);
        self::assertSame(64.0, $packet->itemUseTransaction->playerY);
        self::assertSame(0.5, $packet->itemUseTransaction->clickX);

        $withNetworkItem = substr($wire, 0, 70) . "\x05\0\1\0\2\1\3\x09\0" . substr($wire, 78);
        $packet = PlayerAuthInputPacket::decode($withNetworkItem);
        self::assertInstanceOf(PlayerItemUseTransaction::class, $packet->itemUseTransaction);
        self::assertSame(5, $packet->itemUseTransaction->itemRuntimeId);
        self::assertSame(1, $packet->itemUseTransaction->itemCount);
        self::assertSame(2, $packet->itemUseTransaction->itemAux);
        self::assertSame(-2, $packet->itemUseTransaction->itemStackNetworkId);
        self::assertSame(9, $packet->itemUseTransaction->itemBlockRuntimeId);

        $attackAndCooldown = substr($wire, 0, 62) . "\6" . substr($wire, 63, 41) . "\1" . substr($wire, 105);
        $packet = PlayerAuthInputPacket::decode($attackAndCooldown);
        self::assertInstanceOf(PlayerItemUseTransaction::class, $packet->itemUseTransaction);
        self::assertSame(3, $packet->itemUseTransaction->actionType);
        self::assertSame(1, $packet->itemUseTransaction->clientCooldownState);
    }

    public function testItemUseCountsAndEveryTruncationAreRejected(): void
    {
        $wire = hex2bin(self::ITEM_USE_VECTOR);
        self::assertIsString($wire);
        for ($length = 0; $length < strlen($wire); ++$length) {
            try {
                PlayerAuthInputPacket::decode(substr($wire, 0, $length));
                self::fail("Truncated item-use PlayerAuthInput was accepted at {$length} bytes.");
            } catch (CodecException) {
                self::addToAssertionCount(1);
            }
        }

        foreach ([
            substr($wire, 0, 61) . "\x65" . substr($wire, 62), // 101 inventory actions
            substr($wire, 0, 60) . "\1" . substr($wire, 61), // legacy slots forbidden for request ID zero
            substr($wire, 0, 63) . "\3" . substr($wire, 64), // unknown trigger type
            substr($wire, 0, 77) . "\x81\x80\x40" . substr($wire, 78), // oversized item user data
            substr($wire, 0, 103) . "\2" . substr($wire, 104), // unknown prediction result
            substr($wire, 0, 104) . "\2" . substr($wire, 105), // unknown cooldown state
        ] as $malformed) {
            try {
                PlayerAuthInputPacket::decode($malformed);
                self::fail('Malformed PlayerAuthInput item-use field was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testCurrentInventorySourceAndSingleNetworkIdVectorDecodes(): void
    {
        $wire = hex2bin(self::ITEM_USE_WITH_ACTION_VECTOR);
        self::assertIsString($wire);

        $packet = PlayerAuthInputPacket::decode($wire);

        self::assertInstanceOf(PlayerItemUseTransaction::class, $packet->itemUseTransaction);
        self::assertSame(1, $packet->itemUseTransaction->inventoryActionCount);
        self::assertCount(1, $packet->itemUseTransaction->inventoryActions);
        self::assertContainsOnlyInstancesOf(InventoryAction::class, $packet->itemUseTransaction->inventoryActions);
        self::assertSame(InventorySourceType::Container, $packet->itemUseTransaction->inventoryActions[0]->source->type);
        self::assertSame(-2, $packet->itemUseTransaction->inventoryActions[0]->source->containerId);
        self::assertSame(2, $packet->itemUseTransaction->inventoryActions[0]->slot);
        self::assertSame(5, $packet->itemUseTransaction->inventoryActions[0]->fromItem->runtimeId);
        self::assertSame(0, $packet->itemUseTransaction->inventoryActions[0]->toItem->runtimeId);
        self::assertEquals(new InventoryItemStack(
            $packet->itemUseTransaction->itemRuntimeId,
            $packet->itemUseTransaction->itemCount,
            $packet->itemUseTransaction->itemAux,
            $packet->itemUseTransaction->itemStackNetworkId,
            $packet->itemUseTransaction->itemBlockRuntimeId,
            '',
        ), $packet->itemUseTransaction->item);
        self::assertSame(0, $packet->itemUseTransaction->hand);
    }

    public function testCurrentHandSourcePresenceAndNetworkIdPresenceAreValidated(): void
    {
        $empty = hex2bin(self::ITEM_USE_VECTOR);
        $withAction = hex2bin(self::ITEM_USE_WITH_ACTION_VECTOR);
        self::assertIsString($empty);
        self::assertIsString($withAction);

        foreach ([
            substr($empty, 0, 69) . "\2" . substr($empty, 70), // hand must be main or off hand
            substr($withAction, 0, 63) . "\0" . substr($withAction, 64), // container source requires its container
            substr($withAction, 0, 72) . "\2" . substr($withAction, 73), // network-ID presence must be boolean
        ] as $malformed) {
            $this->expectMalformedItemUse($malformed);
        }
    }

    public function testItemUseAndBlockActionsCanAppearInTheSameTick(): void
    {
        $itemUse = hex2bin(self::ITEM_USE_VECTOR);
        self::assertIsString($itemUse);
        $bothFlags = substr($itemUse, 0, 32) . "\2\x44\x46" . substr($itemUse, 34);
        $wire = substr($bothFlags, 0, 107) . "\1\1\x04" . substr($bothFlags, 108);

        $packet = PlayerAuthInputPacket::decode($wire);

        self::assertInstanceOf(PlayerItemUseTransaction::class, $packet->itemUseTransaction);
        self::assertEquals([
            new PlayerBlockAction(PlayerActionType::StopDestroyBlock),
        ], $packet->blockActions);
        self::assertSame(0.0, $packet->rawMoveZ, 'Fields following targetless StopDestroyBlock remain aligned.');
    }

    public function testTargetlessStopDestroyRoundTripsWithoutInventedCoordinates(): void
    {
        $packet = new PlayerAuthInputPacket(
            1.0, 2.0, 3.0, 65.621, 4.0, 0.0, 0.0, 2.0,
            [PlayerAuthInputFlag::PerformBlockActions->value], 1, 0, 0,
            0.0, 0.0, UnsignedLong::fromInt(7), 0.0, 0.0, 0.0,
            0.0, 0.0, 8.0, 9.0, 10.0, 11.0, 12.0,
            true, null, [new PlayerBlockAction(PlayerActionType::StopDestroyBlock)],
        );

        $decoded = PlayerAuthInputPacket::decode($packet->encode());
        self::assertEquals([new PlayerBlockAction(PlayerActionType::StopDestroyBlock)], $decoded->blockActions);
        self::assertEqualsWithDelta(65.621, $decoded->wireY, 0.000_01);
        self::assertSame(12.0, $decoded->rawMoveZ);
    }

    private function expectMalformedItemUse(string $wire): void
    {
        try {
            PlayerAuthInputPacket::decode($wire);
            self::fail('Malformed current PlayerAuthInput item-use data was accepted.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }
    }
}
