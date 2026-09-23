<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One current Bedrock subchunk with one or more runtime-ID block layers. */
final readonly class ChunkSectionData
{
    public const int CELL_COUNT = 16 * 16 * 16;
    private const int MAX_LAYERS = 8;

    /** @param list<PalettedStorage|PackedPalettedStorage> $blockLayers */
    public function __construct(
        public int $sectionY,
        public array $blockLayers,
    ) {
        if ($sectionY < -128 || $sectionY > 127 || !array_is_list($blockLayers)
            || $blockLayers === [] || count($blockLayers) > self::MAX_LAYERS) {
            throw new InvalidValueException('Chunk section Y or block-layer count is invalid.');
        }
        foreach ($blockLayers as $layer) {
            if ((!$layer instanceof PalettedStorage && !$layer instanceof PackedPalettedStorage)
                || $layer->size() !== self::CELL_COUNT) {
                throw new InvalidValueException('Chunk section contains an invalid block layer.');
            }
        }
    }

    /** @param list<int> $runtimeIds X-Z-Y ordered values. */
    public static function fromRuntimeIds(int $sectionY, array $runtimeIds): self
    {
        return new self($sectionY, [PalettedStorage::fromValues($runtimeIds, self::CELL_COUNT)]);
    }

    public static function allAir(int $sectionY, int $airRuntimeId): self
    {
        return new self($sectionY, [PalettedStorage::singleton($airRuntimeId, self::CELL_COUNT)]);
    }
}
