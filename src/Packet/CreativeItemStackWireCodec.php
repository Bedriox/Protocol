<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Current NetworkItemInstanceDescriptorData codec used by creative content. */
final class CreativeItemStackWireCodec
{
    public static function validate(InventoryItemStack $item): void
    {
        if ($item->stackNetworkId !== null) {
            throw new InvalidValueException('Creative item instances cannot carry stack-network IDs.');
        }
        if ($item->runtimeId === 0 && ($item->count !== 0 || $item->aux !== 0 || $item->blockRuntimeId !== 0 || $item->userData !== '')) {
            throw new InvalidValueException('Creative air item instances must be empty.');
        }
        if ($item->runtimeId !== 0 && $item->count < 1) {
            throw new InvalidValueException('Creative non-air item instances require a positive count.');
        }
    }

    public static function write(ByteBufferWriter $writer, InventoryItemStack $item): ByteBufferWriter
    {
        self::validate($item);
        return $writer->writeSignedVarInt($item->runtimeId)
            ->writeUnsignedShortLE($item->count)
            ->writeUnsignedVarInt($item->aux)
            ->writeSignedVarInt($item->blockRuntimeId)
            ->writeUnsignedVarInt(strlen($item->userData))
            ->writeBytes($item->userData);
    }

    /** @return array{InventoryItemStack, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $runtimeId = $reader->readSignedVarInt();
        if ($runtimeId->value < -0x8000 || $runtimeId->value > 0x7fff) {
            throw new MalformedDataException('Creative item runtime ID exceeds its supported range.');
        }
        $count = $runtimeId->reader->readUnsignedShortLE();
        $aux = $count->reader->readUnsignedVarInt();
        if ($aux->value > 0x7fff) {
            throw new MalformedDataException('Creative item auxiliary value exceeds its limit.');
        }
        $blockRuntimeId = $aux->reader->readSignedVarInt();
        $length = $blockRuntimeId->reader->readUnsignedVarInt();
        if ($length->value > InventoryItemStack::MAXIMUM_USER_DATA_BYTES) {
            throw new MalformedDataException('Creative item user data exceeds its byte limit.');
        }
        $userData = $length->reader->readBytes($length->value);
        try {
            $item = new InventoryItemStack(
                $runtimeId->value,
                $count->value,
                $aux->value,
                null,
                $blockRuntimeId->value,
                $userData->value,
            );
            self::validate($item);
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Creative item instance is invalid.', previous: $e);
        }
        return [$item, $userData->reader];
    }
}
