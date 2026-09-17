<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class SubChunkResponse
{
    public const int SUCCESS = 1;
    public const int CHUNK_NOT_FOUND = 2;
    public const int INDEX_OUT_OF_BOUNDS = 5;
    public const int SUCCESS_ALL_AIR = 6;

    public const int HEIGHT_NONE = 0;
    public const int HEIGHT_DATA = 1;
    public const int HEIGHT_TOO_HIGH = 2;
    public const int HEIGHT_TOO_LOW = 3;

    public function __construct(
        public int $offsetX,
        public int $offsetY,
        public int $offsetZ,
        public int $result,
        public ?string $data,
        public int $heightMapType,
        public ?string $heightMap,
        public int $renderHeightMapType,
        public ?string $renderHeightMap,
    ) {
        foreach ([$offsetX, $offsetY, $offsetZ] as $offset) {
            if ($offset < -128 || $offset > 127) {
                throw new InvalidValueException('Sub-chunk response offset must fit in a signed byte.');
            }
        }
        if (!in_array($result, [self::SUCCESS, self::CHUNK_NOT_FOUND, self::INDEX_OUT_OF_BOUNDS, self::SUCCESS_ALL_AIR], true)) {
            throw new InvalidValueException('Sub-chunk response result is unsupported.');
        }
        if (($result === self::SUCCESS) !== ($data !== null) || ($data !== null && strlen($data) > CodecSupport::MAX_CHUNK_BYTES)) {
            throw new InvalidValueException('Sub-chunk response data does not match its result.');
        }
        foreach ([[$heightMapType, $heightMap], [$renderHeightMapType, $renderHeightMap]] as [$type, $map]) {
            if ($type < self::HEIGHT_NONE || $type > self::HEIGHT_TOO_LOW
                || (($type === self::HEIGHT_DATA) !== ($map !== null)) || ($map !== null && strlen($map) !== 256)) {
                throw new InvalidValueException('Sub-chunk response heightmap is invalid.');
            }
        }
    }
}
