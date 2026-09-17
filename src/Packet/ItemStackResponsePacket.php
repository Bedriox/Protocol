<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded clientbound protocol-2193 item-stack responses. */
final readonly class ItemStackResponsePacket implements Packet
{
    public const int STATUS_ERROR = ItemStackResponse::STATUS_ERROR;
    public const int MAXIMUM_RESPONSES = 128;

    /** @var list<ItemStackResponse> */
    public array $responses;
    /** @var list<int> Backward-compatible request-ID projection. */
    public array $requestIds;

    /** @param list<ItemStackResponse|int> $responses */
    public function __construct(array $responses)
    {
        if (!array_is_list($responses) || $responses === [] || count($responses) > self::MAXIMUM_RESPONSES) {
            throw new InvalidValueException('Item-stack responses must be a non-empty bounded list.');
        }
        $typed = [];
        foreach ($responses as $response) {
            if (is_int($response)) {
                $response = new ItemStackResponse(ItemStackResponse::STATUS_ERROR, $response);
            }
            if (!$response instanceof ItemStackResponse) {
                throw new InvalidValueException('Item-stack responses must contain typed values.');
            }
            $typed[] = $response;
        }
        $this->responses = $typed;
        $this->requestIds = array_map(static fn (ItemStackResponse $response): int => $response->requestId, $typed);
    }

    public function packetId(): int { return PacketIds::ITEM_STACK_RESPONSE; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->responses));
        foreach ($this->responses as $response) {
            $writer = $writer->writeUnsignedByte($response->result)->writeSignedVarInt($response->requestId);
            $writer = CodecSupport::writeBoolean($writer, $response->containers !== []);
            if ($response->containers !== []) {
                $writer = $writer->writeUnsignedVarInt(count($response->containers));
                foreach ($response->containers as $container) {
                    $writer = self::writeContainer($writer, $container);
                }
            }
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value < 1 || $count->value > self::MAXIMUM_RESPONSES) {
            throw new MalformedDataException('Item-stack response count is invalid.');
        }
        $responses = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            [$responses[], $reader] = self::readResponse($reader);
        }
        CodecSupport::requireEnd($reader);
        return new self($responses);
    }

    /** @return array{ItemStackResponse, ByteBufferReader} */
    private static function readResponse(ByteBufferReader $reader): array
    {
        $result = $reader->readUnsignedByte();
        $requestId = $result->reader->readSignedVarInt();
        [$hasContainers, $reader] = CodecSupport::readBoolean($requestId->reader);
        if ($result->value !== ItemStackResponse::STATUS_SUCCESS && $hasContainers) {
            throw new MalformedDataException('Failed item-stack response cannot contain mutations.');
        }
        $containers = [];
        if ($hasContainers) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value > ItemStackResponse::MAXIMUM_CONTAINERS) {
                throw new MalformedDataException('Item-stack response container count exceeds its limit.');
            }
            $reader = $count->reader;
            for ($index = 0; $index < $count->value; ++$index) {
                [$containers[], $reader] = self::readContainer($reader);
            }
        }
        try {
            return [new ItemStackResponse($result->value, $requestId->value, $containers), $reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Item-stack response is invalid.', previous: $e);
        }
    }

    /** @return array{ItemStackResponseContainer, ByteBufferReader} */
    private static function readContainer(ByteBufferReader $reader): array
    {
        [$containerName, $reader] = FullContainerNameWireCodec::read($reader);
        $count = $reader->readUnsignedVarInt();
        if ($count->value > ItemStackResponseContainer::MAXIMUM_SLOTS) {
            throw new MalformedDataException('Item-stack response slot count exceeds its limit.');
        }
        $slots = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            [$slots[], $reader] = self::readSlot($reader);
        }
        return [new ItemStackResponseContainer($containerName, $slots), $reader];
    }

    /** @return array{ItemStackResponseSlot, ByteBufferReader} */
    private static function readSlot(ByteBufferReader $reader): array
    {
        $requested = $reader->readUnsignedByte();
        $slot = $requested->reader->readUnsignedByte();
        $amount = $slot->reader->readUnsignedByte();
        [$hasNetworkId, $reader] = CodecSupport::readBoolean($amount->reader);
        $networkId = null;
        if ($hasNetworkId) {
            $id = $reader->readSignedVarInt();
            $networkId = $id->value;
            $reader = $id->reader;
        }
        $customName = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$hasFilteredName, $reader] = CodecSupport::readBoolean($customName->reader);
        $filteredName = null;
        if ($hasFilteredName) {
            $value = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $filteredName = $value->value;
            $reader = $value->reader;
        }
        $durability = $reader->readSignedVarInt();
        try {
            return [new ItemStackResponseSlot(
                $requested->value,
                $slot->value,
                $amount->value,
                $networkId,
                $customName->value,
                $filteredName,
                $durability->value,
            ), $durability->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Item-stack response slot is invalid.', previous: $e);
        }
    }

    private static function writeContainer(ByteBufferWriter $writer, ItemStackResponseContainer $container): ByteBufferWriter
    {
        $writer = FullContainerNameWireCodec::write($writer, $container->containerName)
            ->writeUnsignedVarInt(count($container->slots));
        foreach ($container->slots as $slot) {
            $writer = self::writeSlot($writer, $slot);
        }
        return $writer;
    }

    private static function writeSlot(ByteBufferWriter $writer, ItemStackResponseSlot $slot): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedByte($slot->requestedSlot)->writeUnsignedByte($slot->slot)
            ->writeUnsignedByte($slot->amount);
        $writer = CodecSupport::writeBoolean($writer, $slot->stackNetworkId !== null);
        if ($slot->stackNetworkId !== null) {
            $writer = $writer->writeSignedVarInt($slot->stackNetworkId);
        }
        $writer = $writer->writeString($slot->customName, CodecSupport::MAX_SHORT_STRING_BYTES);
        $writer = CodecSupport::writeBoolean($writer, $slot->filteredCustomName !== null);
        if ($slot->filteredCustomName !== null) {
            $writer = $writer->writeString($slot->filteredCustomName, CodecSupport::MAX_SHORT_STRING_BYTES);
        }
        return $writer->writeSignedVarInt($slot->durabilityCorrection);
    }
}
