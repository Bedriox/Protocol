<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Value\UnsignedLong;

final readonly class SetActorMotionPacket implements Packet
{
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public float $motionX,
        public float $motionY,
        public float $motionZ,
    ) {
        CodecSupport::validateFiniteFloat($motionX, 'Actor motion X');
        CodecSupport::validateFiniteFloat($motionY, 'Actor motion Y');
        CodecSupport::validateFiniteFloat($motionZ, 'Actor motion Z');
    }

    public function packetId(): int { return PacketIds::SET_ACTOR_MOTION; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeFloatLE($this->motionX)
            ->writeFloatLE($this->motionY)
            ->writeFloatLE($this->motionZ)
            ->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $motionX = $runtimeEntityId->reader->readFloatLE();
        $motionY = $motionX->reader->readFloatLE();
        $motionZ = $motionY->reader->readFloatLE();
        CodecSupport::validateFiniteFloat($motionX->value, 'Actor motion X', true);
        CodecSupport::validateFiniteFloat($motionY->value, 'Actor motion Y', true);
        CodecSupport::validateFiniteFloat($motionZ->value, 'Actor motion Z', true);
        CodecSupport::requireEnd($motionZ->reader);
        return new self($runtimeEntityId->value, $motionX->value, $motionY->value, $motionZ->value);
    }
}
