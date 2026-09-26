<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class UpdateAttributesPacket implements Packet
{
    /** @param list<ActorAttribute> $attributes */
    public function __construct(public UnsignedLong $runtimeEntityId, public array $attributes, public UnsignedLong $tick)
    {
        CodecSupport::validateCount($attributes, 32, 'Actor attributes');
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof ActorAttribute) { throw new InvalidValueException('Attributes must be ActorAttribute values.'); }
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

    public static function decode(string $bytes): self
    {
        $runtimeEntityId = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $count = $runtimeEntityId->reader->readUnsignedVarInt();
        if ($count->value > 32) {
            throw new MalformedDataException('Actor attribute count exceeds its limit.');
        }
        $reader = $count->reader;
        $attributes = [];
        for ($index = 0; $index < $count->value; ++$index) {
            $minimum = $reader->readFloatLE();
            $maximum = $minimum->reader->readFloatLE();
            $value = $maximum->reader->readFloatLE();
            $defaultMinimum = $value->reader->readFloatLE();
            $defaultMaximum = $defaultMinimum->reader->readFloatLE();
            $default = $defaultMaximum->reader->readFloatLE();
            foreach ([$minimum->value, $maximum->value, $value->value, $defaultMinimum->value,
                $defaultMaximum->value, $default->value] as $number) {
                CodecSupport::validateFiniteFloat($number, 'Actor attribute value', true);
            }
            $name = $default->reader->readString(128);
            $modifierCount = $name->reader->readUnsignedVarInt();
            if ($modifierCount->value > 64) {
                throw new MalformedDataException('Actor attribute modifier count exceeds its limit.');
            }
            $reader = $modifierCount->reader;
            $modifiers = [];
            for ($modifierIndex = 0; $modifierIndex < $modifierCount->value; ++$modifierIndex) {
                $id = $reader->readString(128);
                $modifierName = $id->reader->readString(128);
                $amount = $modifierName->reader->readFloatLE();
                CodecSupport::validateFiniteFloat($amount->value, 'Actor attribute modifier amount', true);
                $operationId = $amount->reader->readSignedIntLE();
                $operation = ActorAttributeOperation::tryFrom($operationId->value);
                if ($operation === null) {
                    throw new MalformedDataException('Actor attribute modifier operation is invalid.');
                }
                $operand = $operationId->reader->readSignedIntLE();
                [$serializable, $reader] = CodecSupport::readBoolean($operand->reader);
                try {
                    $modifiers[] = new ActorAttributeModifier(
                        $id->value,
                        $modifierName->value,
                        $amount->value,
                        $operation,
                        $operand->value,
                        $serializable,
                    );
                } catch (InvalidValueException $e) {
                    throw new MalformedDataException('Actor attribute modifier is invalid.', previous: $e);
                }
            }
            try {
                $attributes[] = new ActorAttribute(
                    $name->value,
                    $minimum->value,
                    $maximum->value,
                    $value->value,
                    $defaultMinimum->value,
                    $defaultMaximum->value,
                    $default->value,
                    $modifiers,
                );
            } catch (InvalidValueException $e) {
                throw new MalformedDataException('Actor attribute is invalid.', previous: $e);
            }
        }
        $tick = $reader->readUnsignedVarLong();
        CodecSupport::requireEnd($tick->reader);

        return new self($runtimeEntityId->value, $attributes, $tick->value);
    }

    private static function writeAttribute(ByteBufferWriter $writer, ActorAttribute $attribute): ByteBufferWriter
    {
        $writer = $writer->writeFloatLE($attribute->minimum)->writeFloatLE($attribute->maximum)
            ->writeFloatLE($attribute->value)->writeFloatLE($attribute->defaultMinimum)
            ->writeFloatLE($attribute->defaultMaximum)->writeFloatLE($attribute->default)
            ->writeString($attribute->name, 128)
            ->writeUnsignedVarInt(count($attribute->modifiers));
        foreach ($attribute->modifiers as $modifier) {
            $writer = $writer->writeString($modifier->id, 128)
                ->writeString($modifier->name, 128)
                ->writeFloatLE($modifier->amount)
                ->writeSignedIntLE($modifier->operation->value)
                ->writeSignedIntLE($modifier->operand);
            $writer = CodecSupport::writeBoolean($writer, $modifier->serializable);
        }
        return $writer;
    }
}
