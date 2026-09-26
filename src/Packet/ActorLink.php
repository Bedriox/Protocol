<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\MalformedDataException;

/** One current-protocol vehicle/passenger link between authoritative actor unique IDs. */
final readonly class ActorLink
{
    public function __construct(
        public int $fromActorUniqueId,
        public int $toActorUniqueId,
        public ActorLinkType $type,
        public bool $immediate = false,
        public bool $riderInitiated = false,
        public float $vehicleAngularVelocity = 0.0,
    ) {
        CodecSupport::validateFiniteFloat($vehicleAngularVelocity, 'Actor-link vehicle angular velocity');
    }

    public function write(ByteBufferWriter $writer): ByteBufferWriter
    {
        $writer = $writer->writeSignedVarLong($this->fromActorUniqueId)
            ->writeSignedVarLong($this->toActorUniqueId)
            ->writeUnsignedByte($this->type->value);
        $writer = CodecSupport::writeBoolean($writer, $this->immediate);
        $writer = CodecSupport::writeBoolean($writer, $this->riderInitiated);
        return $writer->writeFloatLE($this->vehicleAngularVelocity);
    }

    /** @return array{self, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $from = $reader->readSignedVarLong();
        $to = $from->reader->readSignedVarLong();
        $typeId = $to->reader->readUnsignedByte();
        $type = ActorLinkType::tryFrom($typeId->value);
        if ($type === null) {
            throw new MalformedDataException('Actor-link type is invalid.');
        }
        [$immediate, $reader] = CodecSupport::readBoolean($typeId->reader);
        [$riderInitiated, $reader] = CodecSupport::readBoolean($reader);
        $angularVelocity = $reader->readFloatLE();
        CodecSupport::validateFiniteFloat($angularVelocity->value, 'Actor-link vehicle angular velocity', true);

        return [new self(
            $from->value,
            $to->value,
            $type,
            $immediate,
            $riderInitiated,
            $angularVelocity->value,
        ), $angularVelocity->reader];
    }
}
