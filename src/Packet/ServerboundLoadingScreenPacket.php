<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Client notification for Bedrock world loading-screen transitions. */
final readonly class ServerboundLoadingScreenPacket implements Packet
{
    public const int UNKNOWN = 0;
    public const int START = 1;
    public const int END = 2;

    public function __construct(public int $type, public ?int $loadingScreenId)
    {
        if ($type < self::UNKNOWN || $type > self::END) {
            throw new InvalidValueException('Loading-screen packet type is invalid.');
        }
        if ($loadingScreenId !== null && ($loadingScreenId < 0 || $loadingScreenId > 0xffffffff)) {
            throw new InvalidValueException('Loading-screen ID is outside the unsigned 32-bit range.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::SERVERBOUND_LOADING_SCREEN;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarInt($this->type);
        $writer = CodecSupport::writeBoolean($writer, $this->loadingScreenId !== null);
        if ($this->loadingScreenId !== null) {
            $writer = $writer->writeUnsignedIntLE($this->loadingScreenId);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $type = CodecSupport::reader($bytes)->readSignedVarInt();
        [$hasScreenId, $reader] = CodecSupport::readBoolean($type->reader);
        $screenId = null;
        if ($hasScreenId) {
            $decodedScreenId = $reader->readUnsignedIntLE();
            $screenId = $decodedScreenId->value;
            $reader = $decodedScreenId->reader;
        }
        CodecSupport::requireEnd($reader);
        if ($type->value < self::UNKNOWN || $type->value > self::END) {
            throw new MalformedDataException('Loading-screen packet type is invalid.');
        }

        return new self($type->value, $screenId);
    }
}
