<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\BlockNetworkId;

/** Internal bounded decoder for the current packed PlayerAuthInput item-use form. */
final class PlayerItemUseTransactionCodec
{
    private const int MAX_LEGACY_SLOT_SETS = 1_024;
    private const int MAX_LEGACY_SLOTS = 89;
    private const int MAX_INVENTORY_ACTIONS = 100;

    /** @return array{PlayerItemUseTransaction, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $legacyId = $reader->readSignedVarInt();
        [$legacyPresent, $reader] = CodecSupport::readBoolean($legacyId->reader);
        $expectsLegacy = $legacyId->value < -1 && ($legacyId->value & 1) === 0;
        if ($legacyPresent !== $expectsLegacy) {
            throw new MalformedDataException('PlayerAuthInput legacy-slot presence does not match the request ID.');
        }
        $legacySlotCount = 0;
        if ($legacyPresent) {
            $legacyCount = $reader->readUnsignedVarInt();
            if ($legacyCount->value > self::MAX_LEGACY_SLOT_SETS) {
                throw new MalformedDataException('PlayerAuthInput legacy-slot count exceeds its limit.');
            }
            $legacySlotCount = $legacyCount->value;
            $reader = $legacyCount->reader;
            for ($index = 0; $index < $legacySlotCount; ++$index) {
                $container = $reader->readUnsignedByte();
                $slots = $container->reader->readUnsignedVarInt();
                if ($slots->value > self::MAX_LEGACY_SLOTS) {
                    throw new MalformedDataException('PlayerAuthInput legacy slot list exceeds its limit.');
                }
                $reader = $slots->reader->readBytes($slots->value)->reader;
            }
        }

        $actionCount = $reader->readUnsignedVarInt();
        if ($actionCount->value > self::MAX_INVENTORY_ACTIONS) {
            throw new MalformedDataException('PlayerAuthInput inventory-action count exceeds its limit.');
        }
        $reader = $actionCount->reader;
        $actions = [];
        for ($index = 0; $index < $actionCount->value; ++$index) {
            [$source, $reader] = self::readInventorySource($reader);
            $slot = $reader->readUnsignedVarInt();
            [$fromItem, $reader] = InventoryItemStackWireCodec::read($slot->reader);
            [$toItem, $reader] = InventoryItemStackWireCodec::read($reader);
            $actions[] = new InventoryAction($source, $slot->value, $fromItem, $toItem);
        }

        $actionType = $reader->readSignedVarInt();
        $triggerType = $actionType->reader->readUnsignedByte();
        $x = $triggerType->reader->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        $face = $z->reader->readUnsignedByte();
        $hotbar = $face->reader->readSignedVarInt();
        $hand = $hotbar->reader->readUnsignedByte();
        if ($hand->value > 1) {
            throw new MalformedDataException('PlayerAuthInput item-use hand is unknown.');
        }
        [$item, $reader] = InventoryItemStackWireCodec::read($hand->reader);
        $vectors = [];
        for ($index = 0; $index < 6; ++$index) {
            $float = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($float->value, 'PlayerAuthInput item-use vector', true);
            $vectors[] = $float->value;
            $reader = $float->reader;
        }
        $targetRuntimeId = $reader->readUnsignedVarInt();
        $predictedResult = $targetRuntimeId->reader->readUnsignedByte();
        $cooldown = $predictedResult->reader->readUnsignedByte();

        try {
            $value = new PlayerItemUseTransaction(
                $legacyId->value, $legacySlotCount, $actionCount->value, $actionType->value, $triggerType->value,
                new BlockPosition($x->value, $y->value, $z->value), $face->value, $hotbar->value, $hand->value,
                $item->runtimeId, $item->count, $item->aux, $item->stackNetworkId, $item->blockRuntimeId,
                $vectors[0], $vectors[1], $vectors[2], $vectors[3], $vectors[4], $vectors[5],
                BlockNetworkId::fromUnsigned($targetRuntimeId->value)->signed(), $predictedResult->value, $cooldown->value,
                $actions,
                $item,
            );
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('PlayerAuthInput item-use transaction is invalid.', previous: $e);
        }
        return [$value, $cooldown->reader];
    }

    /** @return array{InventorySource, ByteBufferReader} */
    private static function readInventorySource(ByteBufferReader $reader): array
    {
        $type = $reader->readUnsignedVarInt();
        $sourceType = InventorySourceType::tryFrom($type->value)
            ?? throw new MalformedDataException('PlayerAuthInput inventory source type is unknown.');
        [$containerPresent, $reader] = CodecSupport::readBoolean($type->reader);
        $expectsContainer = in_array($type->value, [0, 99999], true);
        if ($containerPresent !== $expectsContainer) {
            throw new MalformedDataException('PlayerAuthInput inventory source container presence is invalid.');
        }
        $containerId = null;
        if ($containerPresent) {
            $container = $reader->readUnsignedByte();
            $containerId = $container->value > 127 ? $container->value - 256 : $container->value;
            $reader = $container->reader;
        }
        [$flagPresent, $reader] = CodecSupport::readBoolean($reader);
        if ($flagPresent !== ($type->value === 2)) {
            throw new MalformedDataException('PlayerAuthInput inventory source flag presence is invalid.');
        }
        if ($flagPresent) {
            $flag = $reader->readUnsignedVarInt();
            if ($flag->value > 2) {
                throw new MalformedDataException('PlayerAuthInput inventory source flag is unknown.');
            }
            $sourceFlag = InventorySourceFlag::tryFrom($flag->value)
                ?? throw new MalformedDataException('PlayerAuthInput inventory source flag is unknown.');
            return [new InventorySource($sourceType, $containerId, $sourceFlag), $flag->reader];
        }
        return [new InventorySource($sourceType, $containerId), $reader];
    }

}
