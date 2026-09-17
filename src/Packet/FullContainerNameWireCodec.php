<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Internal protocol-2193 FullContainerName encoder. */
final class FullContainerNameWireCodec
{
    /** @return array{FullContainerName, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $containerName = $reader->readUnsignedByte();
        [$hasDynamicId, $reader] = CodecSupport::readBoolean($containerName->reader);
        $dynamicId = null;
        if ($hasDynamicId) {
            $value = $reader->readUnsignedIntLE();
            $dynamicId = $value->value;
            $reader = $value->reader;
        }
        try {
            return [new FullContainerName($containerName->value, $dynamicId), $reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Full container name is invalid.', previous: $e);
        }
    }

    public static function write(ByteBufferWriter $writer, FullContainerName $value): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedByte($value->containerNameId);
        $writer = CodecSupport::writeBoolean($writer, $value->dynamicId !== null);
        return $value->dynamicId === null ? $writer : $writer->writeUnsignedIntLE($value->dynamicId);
    }
}
