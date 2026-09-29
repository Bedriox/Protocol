<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\BlockNetworkId;

/** Internal protocol-2193 NetworkItemStackDescriptor codec shared by transaction forms. */
final class InventoryItemStackWireCodec
{
    /** @return array{InventoryItemStack, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $runtimeId = $reader->readSignedShortLE();
        $count = $runtimeId->reader->readUnsignedShortLE();
        $aux = $count->reader->readUnsignedVarInt();
        if ($aux->value > 0x7fff) {
            throw new MalformedDataException('Inventory item auxiliary value exceeds its limit.');
        }
        [$hasNetworkId, $reader] = CodecSupport::readBoolean($aux->reader);
        $networkId = null;
        if ($hasNetworkId) {
            $id = $reader->readSignedVarInt();
            $networkId = $id->value;
            $reader = $id->reader;
        }
        $blockRuntimeId = $reader->readUnsignedVarInt();
        $userDataLength = $blockRuntimeId->reader->readUnsignedVarInt();
        if ($userDataLength->value > InventoryItemStack::MAXIMUM_USER_DATA_BYTES) {
            throw new MalformedDataException('Inventory item user data exceeds its byte limit.');
        }
        $userData = $userDataLength->reader->readBytes($userDataLength->value);

        return [new InventoryItemStack(
            $runtimeId->value,
            $count->value,
            $aux->value,
            $networkId,
            BlockNetworkId::fromUnsigned($blockRuntimeId->value)->signed(),
            $userData->value,
        ), $userData->reader];
    }

    public static function write(ByteBufferWriter $writer, InventoryItemStack $item): ByteBufferWriter
    {
        $writer = $writer->writeSignedShortLE($item->runtimeId)
            ->writeUnsignedShortLE($item->count)
            ->writeUnsignedVarInt($item->aux);
        $writer = CodecSupport::writeBoolean($writer, $item->stackNetworkId !== null);
        if ($item->stackNetworkId !== null) {
            $writer = $writer->writeSignedVarInt($item->stackNetworkId);
        }

        return $writer->writeUnsignedVarInt(BlockNetworkId::fromSigned($item->blockRuntimeId)->unsigned())
            ->writeUnsignedVarInt(strlen($item->userData))
            ->writeBytes($item->userData);
    }
}
