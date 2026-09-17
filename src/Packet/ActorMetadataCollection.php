<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Shared bounded wire codec for synchronized actor-data lists. */
final class ActorMetadataCollection
{
    private const int MAX_ENTRIES = 128;

    private function __construct()
    {
    }

    /** @param list<ActorMetadata> $metadata */
    public static function validate(array $metadata): void
    {
        CodecSupport::validateCount($metadata, self::MAX_ENTRIES, 'Actor metadata');
        $previousId = -1;
        foreach ($metadata as $entry) {
            if (!$entry instanceof ActorMetadata) {
                throw new InvalidValueException('Actor metadata must contain ActorMetadata values.');
            }
            if ($entry->id <= $previousId) {
                throw new InvalidValueException('Actor metadata IDs must be unique and ascending.');
            }
            $previousId = $entry->id;
        }
    }

    /** @param list<ActorMetadata> $metadata */
    public static function write(ByteBufferWriter $writer, array $metadata): ByteBufferWriter
    {
        self::validate($metadata);
        $writer = $writer->writeUnsignedVarInt(count($metadata));
        foreach ($metadata as $entry) {
            $writer = $entry->write($writer);
        }
        return $writer;
    }

    /** @return array{list<ActorMetadata>, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > self::MAX_ENTRIES) {
            throw new MalformedDataException('Actor metadata count exceeds its limit.');
        }

        $reader = $count->reader;
        $metadata = [];
        $previousId = -1;
        for ($index = 0; $index < $count->value; ++$index) {
            [$entry, $reader] = ActorMetadata::read($reader);
            if ($entry->id <= $previousId) {
                throw new MalformedDataException('Actor metadata IDs must be unique and ascending.');
            }
            $metadata[] = $entry;
            $previousId = $entry->id;
        }
        return [$metadata, $reader];
    }
}
