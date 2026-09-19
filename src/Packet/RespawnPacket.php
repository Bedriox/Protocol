<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class RespawnPacket implements Packet
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
        public RespawnState $state,
        public UnsignedLong $runtimeEntityId,
    ) {
        foreach ([$this->x, $this->y, $this->z] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Respawn position');
        }
    }

    public function packetId(): int { return PacketIds::RESPAWN; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeFloatLE($this->x)->writeFloatLE($this->y)->writeFloatLE($this->z)
            ->writeUnsignedByte($this->state->value)->writeUnsignedVarLong($this->runtimeEntityId)->toString();
    }

    public static function decode(string $bytes): self
    {
        $reader = CodecSupport::reader($bytes);
        $coordinates = [];
        foreach ([0, 1, 2] as $_index) {
            $value = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($value->value, 'Respawn position', true);
            $coordinates[] = $value->value;
            $reader = $value->reader;
        }
        $state = $reader->readUnsignedByte();
        $typedState = RespawnState::tryFrom($state->value);
        if ($typedState === null) {
            throw new MalformedDataException('Respawn state is not defined by the supported Bedrock protocol.');
        }
        $runtime = $state->reader->readUnsignedVarLong();
        CodecSupport::requireEnd($runtime->reader);

        return new self($coordinates[0], $coordinates[1], $coordinates[2], $typedState, $runtime->value);
    }
}
