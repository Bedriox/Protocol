<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class UpdateAttributesPacket implements Packet
{
    /** @param list<PlayerAttribute> $attributes */
    public function __construct(public UnsignedLong $runtimeEntityId, public array $attributes, public UnsignedLong $tick)
    {
        CodecSupport::validateCount($attributes, 32, 'Player attributes');
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof PlayerAttribute) { throw new InvalidValueException('Attributes must be PlayerAttribute values.'); }
        }
    }

    public static function survival(UnsignedLong $runtimeEntityId): self
    {
        $maximum = 3.4028234663852886e38;
        return new self($runtimeEntityId, [
            PlayerAttribute::health(20.0),
            PlayerAttribute::hunger(20.0),
            new PlayerAttribute('minecraft:movement', 0.0, $maximum, 0.1, 0.0, $maximum, 0.1),
            new PlayerAttribute('minecraft:player.level', 0.0, 24_791.0, 0.0, 0.0, 24_791.0, 0.0),
            new PlayerAttribute('minecraft:player.experience', 0.0, 1.0, 0.0, 0.0, 1.0, 0.0),
        ], UnsignedLong::fromInt(0));
    }

    public static function nutrition(
        UnsignedLong $runtimeEntityId,
        float $hunger,
        float $saturation,
        ?UnsignedLong $tick = null,
    ): self {
        return new self($runtimeEntityId, [
            PlayerAttribute::hunger($hunger),
            PlayerAttribute::saturation($saturation),
        ], $tick ?? UnsignedLong::fromInt(0));
    }

    public function packetId(): int { return PacketIds::UPDATE_ATTRIBUTES; }
    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId)->writeUnsignedVarInt(count($this->attributes));
        foreach ($this->attributes as $attribute) {
            $writer = self::writeAttribute($writer, $attribute);
        }
        return $writer->writeUnsignedVarLong($this->tick)->toString();
    }

    private static function writeAttribute(ByteBufferWriter $writer, PlayerAttribute $attribute): ByteBufferWriter
    {
        return $writer->writeFloatLE($attribute->minimum)->writeFloatLE($attribute->maximum)
            ->writeFloatLE($attribute->value)->writeFloatLE($attribute->defaultMinimum)
            ->writeFloatLE($attribute->defaultMaximum)->writeFloatLE($attribute->default)
            ->writeString($attribute->name, 128)->writeUnsignedVarInt(0);
    }
}
