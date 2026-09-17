<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ResourcePackInfoEntry
{
    public function __construct(
        public string $packId,
        public string $packVersion,
        public int $packSize,
        public string $contentKey,
        public string $subPackName,
        public string $contentId,
        public bool $scripting,
        public bool $addonPack,
        public bool $raytracingCapable,
        public string $cdnUrl,
    ) {
        CodecSupport::uuidToWire($packId);
        foreach ([$packVersion, $contentKey, $subPackName, $contentId, $cdnUrl] as $value) {
            CodecSupport::validateString($value, CodecSupport::MAX_SHORT_STRING_BYTES, 'Resource-pack field');
        }
        if ($packSize < 0) {
            throw new InvalidValueException('Resource-pack size cannot be negative.');
        }
    }
}
