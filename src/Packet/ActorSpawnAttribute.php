<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** One bounded attribute in an AddActor spawn snapshot. */
final readonly class ActorSpawnAttribute
{
    public const int MAXIMUM_NAME_BYTES = 128;

    public function __construct(
        public string $name,
        public float $minimum,
        public float $maximum,
        public float $value,
    ) {
        CodecSupport::validateString($name, self::MAXIMUM_NAME_BYTES, 'Actor attribute name');
        if ($name === '') {
            throw new InvalidValueException('Actor attribute name cannot be empty.');
        }
        foreach ([$minimum, $maximum, $value] as $number) {
            CodecSupport::validateFiniteFloat($number, 'Actor attribute value');
        }
        if ($minimum > $maximum || $value < $minimum || $value > $maximum) {
            throw new InvalidValueException('Actor attribute bounds or current value are inconsistent.');
        }
    }

    public function write(ByteBufferWriter $writer): ByteBufferWriter
    {
        return $writer->writeString($this->name, self::MAXIMUM_NAME_BYTES)
            ->writeFloatLE($this->minimum)
            ->writeFloatLE($this->value)
            ->writeFloatLE($this->maximum);
    }

    /** @return array{self, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $name = $reader->readString(self::MAXIMUM_NAME_BYTES);
        $minimum = $name->reader->readFloatLE();
        $value = $minimum->reader->readFloatLE();
        $maximum = $value->reader->readFloatLE();
        foreach ([$minimum->value, $maximum->value, $value->value] as $number) {
            CodecSupport::validateFiniteFloat($number, 'Actor attribute value', true);
        }

        try {
            return [new self($name->value, $minimum->value, $maximum->value, $value->value), $maximum->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Actor spawn attribute is invalid.', previous: $e);
        }
    }
}
