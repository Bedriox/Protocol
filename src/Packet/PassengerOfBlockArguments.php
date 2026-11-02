<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class PassengerOfBlockArguments
{
    public function __construct(
        public BlockPosition $blockPosition,
        public InventoryVector3 $offset,
        public float $rotation,
        public float $rotationLimit,
        public PassengerOfBlockEmoteType $emoteType,
    ) {
        CodecSupport::validateFiniteFloat($rotation, 'Passenger-of-block rotation');
        CodecSupport::validateFiniteFloat($rotationLimit, 'Passenger-of-block rotation limit');
    }

    public function write(ByteBufferWriter $writer): ByteBufferWriter
    {
        return $writer
            ->writeSignedVarInt($this->blockPosition->x)
            ->writeSignedVarInt($this->blockPosition->y)
            ->writeSignedVarInt($this->blockPosition->z)
            ->writeFloatLE($this->offset->x)
            ->writeFloatLE($this->offset->y)
            ->writeFloatLE($this->offset->z)
            ->writeFloatLE($this->rotation)
            ->writeFloatLE($this->rotationLimit)
            ->writeUnsignedByte($this->emoteType->value);
    }

    /** @return array{self, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $x = $reader->readSignedVarInt();
        $y = $x->reader->readSignedVarInt();
        $z = $y->reader->readSignedVarInt();
        $offsetX = $z->reader->readFloatLE();
        $offsetY = $offsetX->reader->readFloatLE();
        $offsetZ = $offsetY->reader->readFloatLE();
        $rotation = $offsetZ->reader->readFloatLE();
        $rotationLimit = $rotation->reader->readFloatLE();
        $emote = $rotationLimit->reader->readUnsignedByte();
        $emoteType = PassengerOfBlockEmoteType::tryFrom($emote->value)
            ?? throw new MalformedDataException('Passenger-of-block emote type is unknown.');

        try {
            return [new self(
                new BlockPosition($x->value, $y->value, $z->value),
                new InventoryVector3($offsetX->value, $offsetY->value, $offsetZ->value),
                $rotation->value,
                $rotationLimit->value,
                $emoteType,
            ), $emote->reader];
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Passenger-of-block arguments are invalid.', previous: $error);
        }
    }
}
