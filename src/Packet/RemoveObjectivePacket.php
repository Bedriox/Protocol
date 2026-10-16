<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class RemoveObjectivePacket implements Packet
{
    public function __construct(public string $objectiveId)
    {
        CodecSupport::validateString($this->objectiveId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Scoreboard objective ID');
        if ($this->objectiveId === '') {
            throw new InvalidValueException('Scoreboard objective ID cannot be empty.');
        }
    }

    public function packetId(): int { return PacketIds::REMOVE_OBJECTIVE; }

    public function encode(): string
    {
        return CodecSupport::writer()
            ->writeString($this->objectiveId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $objectiveId = CodecSupport::reader($bytes)->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        CodecSupport::requireEnd($objectiveId->reader);
        try {
            return new self($objectiveId->value);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException($exception->getMessage(), previous: $exception);
        }
    }
}
