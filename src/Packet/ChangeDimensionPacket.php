<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Clientbound request to enter another dimension at a finite target position. */
final readonly class ChangeDimensionPacket implements Packet
{
    public function __construct(
        public DimensionId $dimension,
        public float $x,
        public float $y,
        public float $z,
        public bool $respawn,
        public ?int $loadingScreenId = null,
    ) {
        foreach (['x' => $x, 'y' => $y, 'z' => $z] as $field => $coordinate) {
            CodecSupport::validateFiniteFloat($coordinate, 'Change-dimension ' . $field);
        }
        if ($loadingScreenId !== null && ($loadingScreenId < 0 || $loadingScreenId > 0xffffffff)) {
            throw new InvalidValueException('Change-dimension loading-screen ID is outside the unsigned 32-bit range.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::CHANGE_DIMENSION;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeSignedVarInt($this->dimension->value)
            ->writeFloatLE($this->x)->writeFloatLE($this->y)->writeFloatLE($this->z);
        $writer = CodecSupport::writeBoolean($writer, $this->respawn);
        $writer = CodecSupport::writeBoolean($writer, $this->loadingScreenId !== null);
        if ($this->loadingScreenId !== null) {
            $writer = $writer->writeUnsignedIntLE($this->loadingScreenId);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $dimension = CodecSupport::reader($bytes)->readSignedVarInt();
        $typedDimension = DimensionId::tryFrom($dimension->value);
        if ($typedDimension === null) {
            throw new MalformedDataException('Change-dimension target is not a vanilla dimension.');
        }

        $coordinates = [];
        $reader = $dimension->reader;
        foreach (['x', 'y', 'z'] as $field) {
            $coordinate = $reader->readFloatLE();
            CodecSupport::validateFiniteFloat($coordinate->value, 'Change-dimension ' . $field, true);
            $coordinates[] = $coordinate->value;
            $reader = $coordinate->reader;
        }
        [$respawn, $reader] = CodecSupport::readBoolean($reader);
        [$hasLoadingScreenId, $reader] = CodecSupport::readBoolean($reader);
        $loadingScreenId = null;
        if ($hasLoadingScreenId) {
            $screenId = $reader->readUnsignedIntLE();
            $loadingScreenId = $screenId->value;
            $reader = $screenId->reader;
        }
        CodecSupport::requireEnd($reader);

        return new self(
            $typedDimension,
            $coordinates[0],
            $coordinates[1],
            $coordinates[2],
            $respawn,
            $loadingScreenId,
        );
    }
}
