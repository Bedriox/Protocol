<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\ItemStackRequestDecodeException;
use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\BufferUnderflowException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Internal bounded protocol-2193 codec for one item-stack request entry. */
final class ItemStackRequestCodec
{
    /** The second action byte is the action enum ordinal, not the wire discriminator. */
    private const array ACTION_MARKERS = [
        0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
        9 => 11, 12 => 14, 17 => 19,
    ];

    /** @return array{ItemStackRequest, ByteBufferReader} */
    public static function readEntry(ByteBufferReader $reader): array
    {
        $requestId = $reader->readSignedVarInt();
        $actionCount = $requestId->reader->readUnsignedVarInt();
        if ($actionCount->value > ItemStackRequest::MAXIMUM_ACTIONS) {
            throw new MalformedDataException('Item-stack request action count exceeds its limit.');
        }
        $actions = [];
        $reader = $actionCount->reader;
        for ($index = 0; $index < $actionCount->value; ++$index) {
            $actionStart = $reader;
            try {
                [$actions[], $reader] = self::readAction($reader);
            } catch (CodecException $failure) {
                $type = null;
                try {
                    $type = $actionStart->readUnsignedVarInt()->value;
                } catch (CodecException) {
                    // An invalid discriminator has no safe numeric action type to report.
                }
                throw new ItemStackRequestDecodeException(
                    'action',
                    self::decodeDetailCode($failure),
                    $actionStart->offset(),
                    $index,
                    $type,
                    $failure,
                );
            }
        }
        $filterStringCount = $reader->readUnsignedVarInt();
        if ($filterStringCount->value > ItemStackRequest::MAXIMUM_FILTER_STRINGS) {
            throw new MalformedDataException('Item-stack request filter-string count exceeds its limit.');
        }
        $filterStrings = [];
        $reader = $filterStringCount->reader;
        for ($index = 0; $index < $filterStringCount->value; ++$index) {
            $string = $reader->readString(ItemStackRequest::MAXIMUM_FILTER_STRING_BYTES);
            CodecSupport::validateWireString($string->value, ItemStackRequest::MAXIMUM_FILTER_STRING_BYTES, 'Item-stack request filter string');
            $filterStrings[] = $string->value;
            $reader = $string->reader;
        }
        $origin = $reader->readSignedIntLE();
        $textProcessingOrigin = $origin->value === -1 ? null : $origin->value;
        try {
            return [new ItemStackRequest($requestId->value, $actions, $filterStrings, $textProcessingOrigin), $origin->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Item-stack request is invalid.', previous: $e);
        }
    }

    private static function decodeDetailCode(CodecException $failure): string
    {
        if ($failure instanceof BufferUnderflowException) {
            return 'truncated';
        }

        return match ($failure->getMessage()) {
            'Item-stack request action type is unsupported.' => 'unsupported_action_type',
            'Item-stack request action type markers disagree.' => 'action_marker_mismatch',
            'Craft-results item count exceeds its limit.' => 'result_count_limit',
            'Craft-results descriptor type is unsupported.' => 'result_descriptor_type',
            'Craft-results descriptor markers disagree.' => 'result_descriptor_marker',
            'Craft-results item user data exceeds its limit.' => 'result_user_data_limit',
            'Item-stack request action is invalid.' => 'invalid_action_value',
            'Truncated unsigned VarInt.',
            'Truncated unsigned VarLong.' => 'truncated',
            default => 'malformed_action',
        };
    }

    public static function writeEntry(ByteBufferWriter $writer, ItemStackRequest $request): ByteBufferWriter
    {
        $writer = $writer->writeSignedVarInt($request->requestId)->writeUnsignedVarInt(count($request->actions));
        foreach ($request->actions as $action) {
            $writer = self::writeAction($writer, $action);
        }
        $writer = $writer->writeUnsignedVarInt(count($request->filterStrings));
        foreach ($request->filterStrings as $string) {
            $writer = $writer->writeString($string, ItemStackRequest::MAXIMUM_FILTER_STRING_BYTES);
        }
        return $writer->writeSignedIntLE($request->textProcessingOrigin ?? -1);
    }

    /** @return array{ItemStackRequestAction, ByteBufferReader} */
    private static function readAction(ByteBufferReader $reader): array
    {
        $type = $reader->readUnsignedVarInt();
        if (!array_key_exists($type->value, self::ACTION_MARKERS)) {
            throw new MalformedDataException('Item-stack request action type is unsupported.');
        }
        $duplicateType = $type->reader->readUnsignedByte();
        if ($duplicateType->value !== self::ACTION_MARKERS[$type->value]) {
            throw new MalformedDataException('Item-stack request action type markers disagree.');
        }
        $reader = $duplicateType->reader;
        try {
            if ($type->value === 0 || $type->value === 1) {
                $amount = $reader->readUnsignedByte();
                [$source, $reader] = self::readSlot($amount->reader);
                [$destination, $reader] = self::readSlot($reader);
                $action = $type->value === 0
                    ? new TakeItemStackRequestAction($amount->value, $source, $destination)
                    : new PlaceItemStackRequestAction($amount->value, $source, $destination);
                return [$action, $reader];
            }
            if ($type->value === 2) {
                [$source, $reader] = self::readSlot($reader);
                [$destination, $reader] = self::readSlot($reader);
                return [new SwapItemStackRequestAction($source, $destination), $reader];
            }
            if ($type->value <= 5) {
                $amount = $reader->readUnsignedByte();
                [$source, $reader] = self::readSlot($amount->reader);
                if ($type->value === 3) {
                    [$randomly, $reader] = CodecSupport::readBoolean($reader);
                    return [new DropItemStackRequestAction($amount->value, $source, $randomly), $reader];
                }
                return [$type->value === 4
                    ? new DestroyItemStackRequestAction($amount->value, $source)
                    : new ConsumeItemStackRequestAction($amount->value, $source), $reader];
            }
            if ($type->value === 6) {
                $slot = $reader->readUnsignedByte();
                return [new CreateItemStackRequestAction($slot->value), $slot->reader];
            }
            if ($type->value === 9) {
                $hotbarSlot = $reader->readSignedVarInt();
                $predictedDurability = $hotbarSlot->reader->readSignedVarInt();
                $stackNetworkId = $predictedDurability->reader->readSignedIntLE();
                return [new MineBlockItemStackRequestAction(
                    $hotbarSlot->value,
                    $predictedDurability->value,
                    $stackNetworkId->value,
                ), $stackNetworkId->reader];
            }
            if ($type->value === 17) {
                $count = $reader->readUnsignedVarInt();
                if ($count->value > CraftResultsItemStackRequestAction::MAXIMUM_RESULTS) {
                    throw new MalformedDataException('Craft-results item count exceeds its limit.');
                }
                $results = [];
                $reader = $count->reader;
                for ($index = 0; $index < $count->value; ++$index) {
                    [$results[], $reader] = self::readResultItem($reader);
                }
                $crafts = $reader->readUnsignedByte();
                return [new CraftResultsItemStackRequestAction($results, $crafts->value), $crafts->reader];
            }
            $networkId = $reader->readUnsignedVarInt();
            $requestedCrafts = $networkId->reader->readUnsignedByte();
            return [new CraftCreativeItemStackRequestAction($networkId->value, $requestedCrafts->value), $requestedCrafts->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Item-stack request action is invalid.', previous: $e);
        }
    }

    /** @return array{ItemStackRequestResultItem, ByteBufferReader} */
    private static function readResultItem(ByteBufferReader $reader): array
    {
        $type = $reader->readUnsignedVarInt();
        if ($type->value > 3) {
            throw new MalformedDataException('Craft-results descriptor type is unsupported.');
        }
        $marker = $type->reader->readUnsignedByte();
        if ($marker->value !== $type->value) {
            throw new MalformedDataException('Craft-results descriptor markers disagree.');
        }
        $reader = $marker->reader;
        $value = null;
        $aux = 0;
        if ($type->value !== 0) {
            $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $value = $name->value;
            $reader = $name->reader;
            if ($type->value === 1) {
                $readAux = $reader->readSignedVarInt();
                $aux = $readAux->value;
                $reader = $readAux->reader;
            } elseif ($type->value === 2) {
                $version = $reader->readSignedShortLE();
                $aux = $version->value;
                $reader = $version->reader;
            }
        }
        $count = $reader->readUnsignedShortLE();
        $blockId = $count->reader->readUnsignedVarInt();
        $length = $blockId->reader->readUnsignedVarInt();
        if ($length->value > InventoryItemStack::MAXIMUM_USER_DATA_BYTES) {
            throw new MalformedDataException('Craft-results item user data exceeds its limit.');
        }
        $userData = $length->reader->readBytes($length->value);
        return [new ItemStackRequestResultItem(
            $type->value, $value, $aux, $count->value, $blockId->value, $userData->value,
        ), $userData->reader];
    }

    /** @return array{ItemStackRequestSlot, ByteBufferReader} */
    private static function readSlot(ByteBufferReader $reader): array
    {
        [$containerName, $reader] = FullContainerNameWireCodec::read($reader);
        $slot = $reader->readUnsignedByte();
        $stackNetworkId = $slot->reader->readSignedIntLE();
        return [new ItemStackRequestSlot($containerName, $slot->value, $stackNetworkId->value), $stackNetworkId->reader];
    }

    private static function writeAction(ByteBufferWriter $writer, ItemStackRequestAction $action): ByteBufferWriter
    {
        $typeId = $action->typeId();
        if (!array_key_exists($typeId, self::ACTION_MARKERS)) {
            throw new InvalidValueException('Item-stack request action type is unsupported.');
        }
        $writer = $writer->writeUnsignedVarInt($typeId)->writeUnsignedByte(self::ACTION_MARKERS[$typeId]);
        if ($action instanceof TakeItemStackRequestAction || $action instanceof PlaceItemStackRequestAction) {
            return self::writeSlot(self::writeSlot($writer->writeUnsignedByte($action->amount), $action->source), $action->destination);
        }
        if ($action instanceof SwapItemStackRequestAction) {
            return self::writeSlot(self::writeSlot($writer, $action->source), $action->destination);
        }
        if ($action instanceof DropItemStackRequestAction) {
            return CodecSupport::writeBoolean(
                self::writeSlot($writer->writeUnsignedByte($action->amount), $action->source),
                $action->isRandomlySelected(),
            );
        }
        if ($action instanceof DestroyItemStackRequestAction || $action instanceof ConsumeItemStackRequestAction) {
            return self::writeSlot($writer->writeUnsignedByte($action->amount), $action->source);
        }
        if ($action instanceof CreateItemStackRequestAction) {
            return $writer->writeUnsignedByte($action->slot);
        }
        if ($action instanceof MineBlockItemStackRequestAction) {
            return $writer->writeSignedVarInt($action->hotbarSlot)
                ->writeSignedVarInt($action->predictedDurability)
                ->writeSignedIntLE($action->stackNetworkId);
        }
        if ($action instanceof CraftCreativeItemStackRequestAction) {
            return $writer->writeUnsignedVarInt($action->creativeItemNetworkId)
                ->writeUnsignedByte($action->requestedCrafts);
        }
        if ($action instanceof CraftResultsItemStackRequestAction) {
            $writer = $writer->writeUnsignedVarInt(count($action->results));
            foreach ($action->results as $result) {
                $writer = $writer->writeUnsignedVarInt($result->descriptorType)
                    ->writeUnsignedByte($result->descriptorType);
                if ($result->descriptorValue !== null) {
                    $writer = $writer->writeString($result->descriptorValue, CodecSupport::MAX_SHORT_STRING_BYTES);
                    if ($result->descriptorType === 1) {
                        $writer = $writer->writeSignedVarInt($result->auxOrVersion);
                    } elseif ($result->descriptorType === 2) {
                        $writer = $writer->writeSignedShortLE($result->auxOrVersion);
                    }
                }
                $writer = $writer->writeUnsignedShortLE($result->count)
                    ->writeUnsignedVarInt($result->blockRuntimeId)
                    ->writeUnsignedVarInt(strlen($result->userData))
                    ->writeBytes($result->userData);
            }
            return $writer->writeUnsignedByte($action->numberOfCrafts);
        }
        if ($action instanceof RejectedItemStackRequestAction) {
            $writer = self::writeSlot($writer->writeUnsignedByte($action->amount), $action->source);
            return $action->randomly === null ? $writer : CodecSupport::writeBoolean($writer, $action->randomly);
        }
        throw new InvalidValueException('Item-stack request action type is unsupported.');
    }

    private static function writeSlot(ByteBufferWriter $writer, ItemStackRequestSlot $slot): ByteBufferWriter
    {
        return FullContainerNameWireCodec::write($writer, $slot->containerName)
            ->writeUnsignedByte($slot->slot)->writeSignedIntLE($slot->stackNetworkId);
    }
}
