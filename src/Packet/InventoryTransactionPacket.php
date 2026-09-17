<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Immutable packet 30 data; applying or rejecting it remains server policy. */
final readonly class InventoryTransactionPacket implements Packet
{
    public const int MAXIMUM_LEGACY_SLOT_SETS = 1_024;
    public const int MAXIMUM_ACTIONS = 100;

    /**
     * @param list<InventoryLegacySlot> $legacySlots
     * @param list<InventoryAction> $actions
     */
    public function __construct(
        public int $legacyRequestId,
        public array $legacySlots,
        public array $actions,
        public InventoryTransactionData $transaction,
    ) {
        if ($legacyRequestId < -0x80000000 || $legacyRequestId > 0x7fffffff) {
            throw new InvalidValueException('Legacy inventory request ID must fit a signed 32-bit integer.');
        }
        CodecSupport::validateCount($legacySlots, self::MAXIMUM_LEGACY_SLOT_SETS, 'Legacy inventory slot sets');
        CodecSupport::validateCount($actions, self::MAXIMUM_ACTIONS, 'Inventory actions');
        foreach ($legacySlots as $legacySlot) {
            if (!$legacySlot instanceof InventoryLegacySlot) {
                throw new InvalidValueException('Legacy inventory slot sets must contain typed values.');
            }
        }
        foreach ($actions as $action) {
            if (!$action instanceof InventoryAction) {
                throw new InvalidValueException('Inventory actions must contain typed values.');
            }
        }
        $allowsLegacySlots = $legacyRequestId < -1 && ($legacyRequestId & 1) === 0;
        if ($legacySlots !== [] && !$allowsLegacySlots) {
            throw new InvalidValueException('Legacy inventory slots require a negative even request ID below -1.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::INVENTORY_TRANSACTION;
    }

    public function encode(): string
    {
        return InventoryTransactionWireCodec::encode($this);
    }

    public static function decode(string $bytes): self
    {
        return InventoryTransactionWireCodec::decode($bytes);
    }
}
