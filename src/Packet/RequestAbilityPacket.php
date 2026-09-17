<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** A client request only; authority remains with the server's ability state. */
final readonly class RequestAbilityPacket implements Packet
{
    public function __construct(
        public int $ability,
        public AbilityValueType $valueType,
        public bool $boolValue,
        public float $floatValue,
    ) {
        if ($ability < 0 || $ability > 19) {
            throw new InvalidValueException('Requested ability is outside the current range.');
        }
        CodecSupport::validateFiniteFloat($floatValue, 'Requested ability float');
    }

    public function packetId(): int { return PacketIds::REQUEST_ABILITY; }

    public function encode(): string
    {
        return CodecSupport::writeBoolean(
            CodecSupport::writer()->writeSignedVarInt($this->ability)->writeUnsignedByte($this->valueType->value),
            $this->boolValue,
        )->writeFloatLE($this->floatValue)->toString();
    }

    public static function decode(string $bytes): self
    {
        $ability = CodecSupport::reader($bytes)->readSignedVarInt();
        if ($ability->value < 0 || $ability->value > 19) {
            throw new MalformedDataException('Requested ability is outside the current range.');
        }
        $type = $ability->reader->readUnsignedByte();
        $valueType = AbilityValueType::tryFrom($type->value);
        if ($valueType === null) {
            throw new MalformedDataException('Requested ability value type is unknown.');
        }
        [$boolValue, $reader] = CodecSupport::readBoolean($type->reader);
        $floatValue = $reader->readFloatLE();
        CodecSupport::validateFiniteFloat($floatValue->value, 'Requested ability float', true);
        CodecSupport::requireEnd($floatValue->reader);
        return new self($ability->value, $valueType, $boolValue, $floatValue->value);
    }
}
