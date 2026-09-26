<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded dynamic-property snapshot carried by actor spawn and metadata updates. */
final readonly class ActorProperties
{
    private const int MAXIMUM_PROPERTIES_PER_KIND = 128;

    /**
     * @param list<ActorIntProperty> $integers
     * @param list<ActorFloatProperty> $floats
     */
    public function __construct(public array $integers = [], public array $floats = [])
    {
        self::validateIntegerEntries($integers);
        self::validateFloatEntries($floats);
    }

    public function write(ByteBufferWriter $writer): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt(count($this->integers));
        foreach ($this->integers as $property) {
            $writer = $writer->writeUnsignedVarInt($property->index)->writeSignedVarInt($property->value);
        }
        $writer = $writer->writeUnsignedVarInt(count($this->floats));
        foreach ($this->floats as $property) {
            $writer = $writer->writeUnsignedVarInt($property->index)->writeFloatLE($property->value);
        }
        return $writer;
    }

    /** @return array{self, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $integerCount = $reader->readUnsignedVarInt();
        if ($integerCount->value > self::MAXIMUM_PROPERTIES_PER_KIND) {
            throw new MalformedDataException('Actor integer-property count exceeds its limit.');
        }
        $reader = $integerCount->reader;
        $integers = [];
        for ($index = 0; $index < $integerCount->value; ++$index) {
            $propertyIndex = $reader->readUnsignedVarInt();
            $propertyValue = $propertyIndex->reader->readSignedVarInt();
            $integers[] = new ActorIntProperty($propertyIndex->value, $propertyValue->value);
            $reader = $propertyValue->reader;
        }

        $floatCount = $reader->readUnsignedVarInt();
        if ($floatCount->value > self::MAXIMUM_PROPERTIES_PER_KIND) {
            throw new MalformedDataException('Actor float-property count exceeds its limit.');
        }
        $reader = $floatCount->reader;
        $floats = [];
        for ($index = 0; $index < $floatCount->value; ++$index) {
            $propertyIndex = $reader->readUnsignedVarInt();
            $propertyValue = $propertyIndex->reader->readFloatLE();
            CodecSupport::validateFiniteFloat($propertyValue->value, 'Actor float-property value', true);
            $floats[] = new ActorFloatProperty($propertyIndex->value, $propertyValue->value);
            $reader = $propertyValue->reader;
        }

        try {
            return [new self($integers, $floats), $reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Actor properties are invalid.', previous: $e);
        }
    }

    /** @param list<ActorIntProperty> $entries */
    private static function validateIntegerEntries(array $entries): void
    {
        CodecSupport::validateCount($entries, self::MAXIMUM_PROPERTIES_PER_KIND, 'Actor integer properties');
        $seen = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof ActorIntProperty) {
                throw new InvalidValueException('Actor integer properties must contain typed values.');
            }
            if (isset($seen[$entry->index])) {
                throw new InvalidValueException('Actor integer property indexes must be unique.');
            }
            $seen[$entry->index] = true;
        }
    }

    /** @param list<ActorFloatProperty> $entries */
    private static function validateFloatEntries(array $entries): void
    {
        CodecSupport::validateCount($entries, self::MAXIMUM_PROPERTIES_PER_KIND, 'Actor float properties');
        $seen = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof ActorFloatProperty) {
                throw new InvalidValueException('Actor float properties must contain typed values.');
            }
            if (isset($seen[$entry->index])) {
                throw new InvalidValueException('Actor float property indexes must be unique.');
            }
            $seen[$entry->index] = true;
        }
    }
}
