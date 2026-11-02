<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class SetPassengerOfBlockPacket implements Packet
{
    public function __construct(public int $entityUniqueId, public ?PassengerOfBlockArguments $data) {}
    public function packetId(): int { return PacketIds::SET_PASSENGER_OF_BLOCK; }
    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarLong($this->entityUniqueId);
        $writer = CodecSupport::writeBoolean($writer, $this->data !== null);
        return ($this->data === null ? $writer : $this->data->write($writer))->toString();
    }
    public static function decode(string $bytes): self
    {
        $entity = CodecSupport::reader($bytes)->readSignedVarLong();
        [$present, $reader] = CodecSupport::readBoolean($entity->reader);
        $data = null;
        if ($present) {
            [$data, $reader] = PassengerOfBlockArguments::read($reader);
        }
        CodecSupport::requireEnd($reader);
        return new self($entity->value, $data);
    }
}
