<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ContainerClosePacket implements Packet
{
    public function __construct(
        public int $containerId,
        public ContainerType $containerType,
        public bool $serverInitiated,
    ) {
        if ($containerId < 0 || $containerId > 255) {
            throw new InvalidValueException('Container close ID must fit one byte.');
        }
    }

    public function packetId(): int { return PacketIds::CONTAINER_CLOSE; }
    public function signedContainerId(): int { return $this->containerId > 127 ? $this->containerId - 256 : $this->containerId; }

    public function encode(): string
    {
        return CodecSupport::writeBoolean(
            CodecSupport::writer()->writeUnsignedByte($this->containerId)
                ->writeUnsignedByte($this->containerType->value & 0xff),
            $this->serverInitiated,
        )->toString();
    }

    public static function decode(string $bytes): self
    {
        $id = CodecSupport::reader($bytes)->readUnsignedByte();
        $type = $id->reader->readUnsignedByte();
        $signedType = $type->value > 0x7f ? $type->value - 0x100 : $type->value;
        $containerType = ContainerType::tryFrom($signedType)
            ?? throw new MalformedDataException('Container-close type is unknown.');
        [$serverInitiated, $reader] = CodecSupport::readBoolean($type->reader);
        CodecSupport::requireEnd($reader);
        return new self($id->value, $containerType, $serverInitiated);
    }
}
