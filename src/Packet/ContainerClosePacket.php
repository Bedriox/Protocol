<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ContainerClosePacket implements Packet
{
    public function __construct(
        public int $containerId,
        public int $containerType,
        public bool $serverInitiated,
    ) {
        if ($containerId < 0 || $containerId > 255 || $containerType < 0 || $containerType > 255) {
            throw new InvalidValueException('Container close identifiers must fit one byte.');
        }
    }

    public function packetId(): int { return PacketIds::CONTAINER_CLOSE; }
    public function signedContainerId(): int { return $this->containerId > 127 ? $this->containerId - 256 : $this->containerId; }

    public function encode(): string
    {
        return CodecSupport::writeBoolean(
            CodecSupport::writer()->writeUnsignedByte($this->containerId)->writeUnsignedByte($this->containerType),
            $this->serverInitiated,
        )->toString();
    }

    public static function decode(string $bytes): self
    {
        $id = CodecSupport::reader($bytes)->readUnsignedByte();
        $type = $id->reader->readUnsignedByte();
        [$serverInitiated, $reader] = CodecSupport::readBoolean($type->reader);
        CodecSupport::requireEnd($reader);
        return new self($id->value, $type->value, $serverInitiated);
    }
}
