<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Clientbound request to spawn a named particle effect in a dimension. */
final readonly class SpawnParticleEffectPacket implements Packet
{
    public const int UNATTACHED_ENTITY_ID = -1;
    public const int MAX_IDENTIFIER_BYTES = 4_096;
    public const int MAX_MOLANG_VARIABLES_BYTES = 65_536;

    public function __construct(
        public DimensionId $dimension,
        public int $uniqueEntityId,
        public LevelEventPosition $position,
        public string $identifier,
        public ?string $molangVariablesJson = null,
    ) {
        CodecSupport::validateString($this->identifier, self::MAX_IDENTIFIER_BYTES, 'Particle identifier');
        if ($this->molangVariablesJson !== null) {
            CodecSupport::validateString(
                $this->molangVariablesJson,
                self::MAX_MOLANG_VARIABLES_BYTES,
                'Particle Molang variables',
            );
        }
    }

    public function packetId(): int
    {
        return PacketIds::SPAWN_PARTICLE_EFFECT;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()
            ->writeUnsignedByte($this->dimension->value)
            ->writeSignedVarLong($this->uniqueEntityId)
            ->writeFloatLE($this->position->x)
            ->writeFloatLE($this->position->y)
            ->writeFloatLE($this->position->z)
            ->writeString($this->identifier, self::MAX_IDENTIFIER_BYTES);
        $writer = CodecSupport::writeBoolean($writer, $this->molangVariablesJson !== null);
        if ($this->molangVariablesJson !== null) {
            $writer = $writer->writeString($this->molangVariablesJson, self::MAX_MOLANG_VARIABLES_BYTES);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $dimensionId = CodecSupport::reader($bytes)->readUnsignedByte();
        $dimension = DimensionId::tryFrom($dimensionId->value);
        if ($dimension === null) {
            throw new MalformedDataException('Particle dimension is invalid for the current protocol.');
        }
        $uniqueEntityId = $dimensionId->reader->readSignedVarLong();
        $x = $uniqueEntityId->reader->readFloatLE();
        $y = $x->reader->readFloatLE();
        $z = $y->reader->readFloatLE();
        foreach ([$x->value, $y->value, $z->value] as $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Particle position', true);
        }
        $identifier = $z->reader->readString(self::MAX_IDENTIFIER_BYTES);
        CodecSupport::validateWireString($identifier->value, self::MAX_IDENTIFIER_BYTES, 'Particle identifier');
        [$hasMolangVariables, $reader] = CodecSupport::readBoolean($identifier->reader);
        $molangVariablesJson = null;
        if ($hasMolangVariables) {
            $molangVariables = $reader->readString(self::MAX_MOLANG_VARIABLES_BYTES);
            CodecSupport::validateWireString(
                $molangVariables->value,
                self::MAX_MOLANG_VARIABLES_BYTES,
                'Particle Molang variables',
            );
            $molangVariablesJson = $molangVariables->value;
            $reader = $molangVariables->reader;
        }
        CodecSupport::requireEnd($reader);

        return new self(
            $dimension,
            $uniqueEntityId->value,
            new LevelEventPosition($x->value, $y->value, $z->value),
            $identifier->value,
            $molangVariablesJson,
        );
    }
}
