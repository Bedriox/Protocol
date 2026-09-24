<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class UpdateSoftEnumPacket implements Packet
{
    /** @param list<string> $values */
    public function __construct(
        public string $enumName,
        public array $values,
        public SoftEnumUpdateType $type,
    ) {
        new CommandEnum($enumName, $values, true);
    }

    public function packetId(): int
    {
        return PacketIds::UPDATE_SOFT_ENUM;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeString($this->enumName, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedVarInt(count($this->values));
        foreach ($this->values as $value) {
            $writer = $writer->writeString($value, CodecSupport::MAX_SHORT_STRING_BYTES);
        }
        return $writer->writeUnsignedByte($this->type->value)->toString();
    }

    public static function decode(string $bytes): self
    {
        $reader = CodecSupport::reader($bytes);
        $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $count = $name->reader->readUnsignedVarInt();
        if ($count->value > AvailableCommandsPacket::MAX_ENUM_VALUES) {
            throw new MalformedDataException('Soft enum update value count exceeds its limit.');
        }
        $values = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $value = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $values[] = $value->value;
            $reader = $value->reader;
        }
        $type = $reader->readUnsignedByte();
        $updateType = SoftEnumUpdateType::tryFrom($type->value);
        if ($updateType === null) {
            throw new MalformedDataException('Soft enum update type is unknown.');
        }
        CodecSupport::requireEnd($type->reader);
        try {
            return new self($name->value, $values, $updateType);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException('Soft enum update contains invalid values.', previous: $exception);
        }
    }
}
