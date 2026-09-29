<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\BlockNetworkId;

/** Internal bounded protocol-2193 packet-30 codec. */
final class InventoryTransactionWireCodec
{
    public static function decode(string $bytes): InventoryTransactionPacket
    {
        $reader = CodecSupport::reader($bytes);
        $legacyId = $reader->readSignedVarInt();
        [$legacyPresent, $reader] = CodecSupport::readBoolean($legacyId->reader);
        $expectsLegacy = $legacyId->value < -1 && ($legacyId->value & 1) === 0;
        if ($legacyPresent !== $expectsLegacy) {
            throw new MalformedDataException('Legacy inventory slot presence does not match the request ID.');
        }
        $legacySlots = [];
        if ($legacyPresent) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value > InventoryTransactionPacket::MAXIMUM_LEGACY_SLOT_SETS) {
                throw new MalformedDataException('Legacy inventory slot-set count exceeds its limit.');
            }
            $reader = $count->reader;
            for ($index = 0; $index < $count->value; ++$index) {
                $container = $reader->readUnsignedByte();
                $length = $container->reader->readUnsignedVarInt();
                if ($length->value > InventoryLegacySlot::MAXIMUM_SLOTS) {
                    throw new MalformedDataException('Legacy inventory slot list exceeds its limit.');
                }
                $slots = $length->reader->readBytes($length->value);
                $legacySlots[] = new InventoryLegacySlot($container->value, $slots->value);
                $reader = $slots->reader;
            }
        }

        $typeValue = $reader->readUnsignedVarInt();
        $type = InventoryTransactionType::tryFrom($typeValue->value)
            ?? throw new MalformedDataException('Inventory transaction type is unknown.');
        $actionCount = $typeValue->reader->readUnsignedVarInt();
        if ($actionCount->value > InventoryTransactionPacket::MAXIMUM_ACTIONS) {
            throw new MalformedDataException('Inventory action count exceeds its limit.');
        }
        $reader = $actionCount->reader;
        $actions = [];
        for ($index = 0; $index < $actionCount->value; ++$index) {
            [$source, $reader] = self::readSource($reader);
            $slot = $reader->readUnsignedVarInt();
            [$from, $reader] = InventoryItemStackWireCodec::read($slot->reader);
            [$to, $reader] = InventoryItemStackWireCodec::read($reader);
            $actions[] = new InventoryAction($source, $slot->value, $from, $to);
        }
        [$transaction, $reader] = self::readTransaction($type, $reader);
        CodecSupport::requireEnd($reader);

        return new InventoryTransactionPacket($legacyId->value, $legacySlots, $actions, $transaction);
    }

    public static function encode(InventoryTransactionPacket $packet): string
    {
        $writer = CodecSupport::writer()->writeSignedVarInt($packet->legacyRequestId);
        $legacyPresent = $packet->legacyRequestId < -1 && ($packet->legacyRequestId & 1) === 0;
        $writer = CodecSupport::writeBoolean($writer, $legacyPresent);
        if ($legacyPresent) {
            $writer = $writer->writeUnsignedVarInt(count($packet->legacySlots));
            foreach ($packet->legacySlots as $legacy) {
                $writer = $writer->writeUnsignedByte($legacy->containerId)
                    ->writeUnsignedVarInt(strlen($legacy->slots))->writeBytes($legacy->slots);
            }
        }
        $writer = $writer->writeUnsignedVarInt($packet->transaction->type()->value)
            ->writeUnsignedVarInt(count($packet->actions));
        foreach ($packet->actions as $action) {
            $writer = self::writeSource($writer, $action->source)->writeUnsignedVarInt($action->slot);
            $writer = InventoryItemStackWireCodec::write($writer, $action->fromItem);
            $writer = InventoryItemStackWireCodec::write($writer, $action->toItem);
        }
        return self::writeTransaction($writer, $packet->transaction)->toString();
    }

    /** @return array{InventorySource, ByteBufferReader} */
    private static function readSource(ByteBufferReader $reader): array
    {
        $typeValue = $reader->readUnsignedVarInt();
        $type = InventorySourceType::tryFrom($typeValue->value)
            ?? throw new MalformedDataException('Inventory source type is unknown.');
        [$hasContainer, $reader] = CodecSupport::readBoolean($typeValue->reader);
        $expectsContainer = $type === InventorySourceType::Container || $type === InventorySourceType::NonImplementedTodo;
        if ($hasContainer !== $expectsContainer) {
            throw new MalformedDataException('Inventory source container presence is invalid.');
        }
        $containerId = null;
        if ($hasContainer) {
            $container = $reader->readUnsignedByte();
            $containerId = $container->value > 127 ? $container->value - 256 : $container->value;
            $reader = $container->reader;
        }
        [$hasFlag, $reader] = CodecSupport::readBoolean($reader);
        if ($hasFlag !== ($type === InventorySourceType::WorldInteraction)) {
            throw new MalformedDataException('Inventory source flag presence is invalid.');
        }
        $flag = null;
        if ($hasFlag) {
            $flagValue = $reader->readUnsignedVarInt();
            $flag = InventorySourceFlag::tryFrom($flagValue->value)
                ?? throw new MalformedDataException('Inventory source flag is unknown.');
            $reader = $flagValue->reader;
        }
        return [new InventorySource($type, $containerId, $flag), $reader];
    }

    private static function writeSource(ByteBufferWriter $writer, InventorySource $source): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt($source->type->value);
        $writer = CodecSupport::writeBoolean($writer, $source->containerId !== null);
        if ($source->containerId !== null) {
            $writer = $writer->writeUnsignedByte($source->containerId & 0xff);
        }
        $writer = CodecSupport::writeBoolean($writer, $source->flag !== null);
        return $source->flag === null ? $writer : $writer->writeUnsignedVarInt($source->flag->value);
    }

    /** @return array{InventoryTransactionData, ByteBufferReader} */
    private static function readTransaction(InventoryTransactionType $type, ByteBufferReader $reader): array
    {
        if ($type === InventoryTransactionType::Normal || $type === InventoryTransactionType::InventoryMismatch) {
            return [new BasicInventoryTransaction($type), $reader];
        }
        if ($type === InventoryTransactionType::ItemUse) {
            $action = self::readEnum($reader, ItemUseActionType::class, 'item-use action');
            $trigger = self::readByteEnum($action[1], ItemUseTriggerType::class, 'item-use trigger');
            [$position, $reader] = self::readBlockPosition($trigger[1]);
            $face = $reader->readUnsignedByte();
            $hotbar = $face->reader->readSignedVarInt();
            $hand = self::readByteEnum($hotbar->reader, HandSlot::class, 'item-use hand');
            [$item, $reader] = InventoryItemStackWireCodec::read($hand[1]);
            [$player, $reader] = self::readVector($reader);
            [$click, $reader] = self::readVector($reader);
            $target = $reader->readUnsignedVarInt();
            $predicted = self::readByteEnum($target->reader, ItemUsePredictedResult::class, 'item-use prediction');
            $cooldown = self::readByteEnum($predicted[1], ItemUseClientCooldownState::class, 'item-use cooldown');
            return [new ItemUseInventoryTransaction($action[0], $trigger[0], $position, $face->value, $hotbar->value,
                $hand[0], $item, $player, $click, BlockNetworkId::fromUnsigned($target->value)->signed(), $predicted[0], $cooldown[0]), $cooldown[1]];
        }
        if ($type === InventoryTransactionType::ItemUseOnEntity) {
            $entity = $reader->readUnsignedVarLong();
            $action = self::readEnum($entity->reader, ItemUseOnEntityActionType::class, 'item-use-on-entity action');
            $hotbar = $action[1]->readSignedVarInt();
            [$item, $reader] = InventoryItemStackWireCodec::read($hotbar->reader);
            [$player, $reader] = self::readVector($reader);
            [$click, $reader] = self::readVector($reader);
            return [new ItemUseOnEntityInventoryTransaction($entity->value, $action[0], $hotbar->value, $item, $player, $click), $reader];
        }
        $action = self::readEnum($reader, ItemReleaseActionType::class, 'item-release action');
        $hotbar = $action[1]->readSignedVarInt();
        [$item, $reader] = InventoryItemStackWireCodec::read($hotbar->reader);
        [$head, $reader] = self::readVector($reader);
        return [new ItemReleaseInventoryTransaction($action[0], $hotbar->value, $item, $head), $reader];
    }

    private static function writeTransaction(ByteBufferWriter $writer, InventoryTransactionData $value): ByteBufferWriter
    {
        if ($value instanceof BasicInventoryTransaction) {
            return $writer;
        }
        if ($value instanceof ItemUseInventoryTransaction) {
            $writer = $writer->writeSignedVarInt($value->action->value)->writeUnsignedByte($value->trigger->value);
            $writer = self::writeBlockPosition($writer, $value->blockPosition)->writeUnsignedByte($value->blockFace)
                ->writeSignedVarInt($value->hotbarSlot)->writeUnsignedByte($value->hand->value);
            $writer = InventoryItemStackWireCodec::write($writer, $value->item);
            return self::writeVector(self::writeVector($writer, $value->playerPosition), $value->clickPosition)
                ->writeUnsignedVarInt(BlockNetworkId::fromSigned($value->targetBlockRuntimeId)->unsigned())->writeUnsignedByte($value->predictedResult->value)
                ->writeUnsignedByte($value->cooldownState->value);
        }
        if ($value instanceof ItemUseOnEntityInventoryTransaction) {
            $writer = $writer->writeUnsignedVarLong($value->runtimeEntityId)->writeSignedVarInt($value->action->value)
                ->writeSignedVarInt($value->hotbarSlot);
            return self::writeVector(self::writeVector(InventoryItemStackWireCodec::write($writer, $value->item), $value->playerPosition), $value->clickPosition);
        }
        if ($value instanceof ItemReleaseInventoryTransaction) {
            $writer = $writer->writeSignedVarInt($value->action->value)->writeSignedVarInt($value->hotbarSlot);
            return self::writeVector(InventoryItemStackWireCodec::write($writer, $value->item), $value->headPosition);
        }
        throw new InvalidValueException('Inventory transaction payload type is unsupported.');
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return array{T, ByteBufferReader}
     */
    private static function readEnum(ByteBufferReader $reader, string $enum, string $field): array
    {
        $value = $reader->readSignedVarInt();
        $case = $enum::tryFrom($value->value);
        if ($case === null) {
            throw new MalformedDataException('Unknown ' . $field . '.');
        }
        return [$case, $value->reader];
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return array{T, ByteBufferReader}
     */
    private static function readByteEnum(ByteBufferReader $reader, string $enum, string $field): array
    {
        $value = $reader->readUnsignedByte();
        $case = $enum::tryFrom($value->value);
        if ($case === null) {
            throw new MalformedDataException('Unknown ' . $field . '.');
        }
        return [$case, $value->reader];
    }

    /** @return array{BlockPosition, ByteBufferReader} */
    private static function readBlockPosition(ByteBufferReader $reader): array
    {
        $x = $reader->readSignedVarInt(); $y = $x->reader->readSignedVarInt(); $z = $y->reader->readSignedVarInt();
        return [new BlockPosition($x->value, $y->value, $z->value), $z->reader];
    }

    private static function writeBlockPosition(ByteBufferWriter $writer, BlockPosition $value): ByteBufferWriter
    {
        return $writer->writeSignedVarInt($value->x)->writeSignedVarInt($value->y)->writeSignedVarInt($value->z);
    }

    /** @return array{InventoryVector3, ByteBufferReader} */
    private static function readVector(ByteBufferReader $reader): array
    {
        $x = $reader->readFloatLE(); $y = $x->reader->readFloatLE(); $z = $y->reader->readFloatLE();
        foreach ([$x->value, $y->value, $z->value] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Inventory transaction vector', true);
        }
        return [new InventoryVector3($x->value, $y->value, $z->value), $z->reader];
    }

    private static function writeVector(ByteBufferWriter $writer, InventoryVector3 $value): ByteBufferWriter
    {
        return $writer->writeFloatLE($value->x)->writeFloatLE($value->y)->writeFloatLE($value->z);
    }
}
