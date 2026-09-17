<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Immutable runtime-ID palette and its dense cell indexes. */
final readonly class PalettedStorage
{
    public const int MAX_ENTRIES = 4_096;
    public const int MAX_PALETTE_SIZE = 4_096;

    /**
     * @param list<int> $palette Runtime IDs in stable palette order.
     * @param list<int> $indices One palette index per X-Z-Y ordered cell.
     */
    public function __construct(
        public array $palette,
        public array $indices,
        public int $minimumBitsPerEntry = 0,
    ) {
        if (!array_is_list($palette) || $palette === [] || count($palette) > self::MAX_PALETTE_SIZE
            || count(array_unique($palette, SORT_REGULAR)) !== count($palette)) {
            throw new InvalidValueException('Paletted storage palette must be a non-empty bounded unique list.');
        }
        foreach ($palette as $runtimeId) {
            if (!self::isRuntimeId($runtimeId)) {
                throw new InvalidValueException('Paletted storage runtime ID is outside the signed-varint range.');
            }
        }
        if (!array_is_list($indices) || $indices === [] || count($indices) > self::MAX_ENTRIES) {
            throw new InvalidValueException('Paletted storage indexes must be a non-empty bounded list.');
        }
        $maximumIndex = count($palette) - 1;
        foreach ($indices as $index) {
            if (!self::isPaletteIndex($index, $maximumIndex)) {
                throw new InvalidValueException('Paletted storage index refers outside its palette.');
            }
        }
        if (!in_array($minimumBitsPerEntry, [0, 1, 2, 3, 4, 5, 6, 8, 16], true)) {
            throw new InvalidValueException('Paletted storage minimum bit width is unsupported.');
        }
    }

    /** @param list<int> $runtimeIds */
    public static function fromValues(array $runtimeIds, int $expectedSize): self
    {
        if ($expectedSize < 1 || $expectedSize > self::MAX_ENTRIES || count($runtimeIds) !== $expectedSize
            || !array_is_list($runtimeIds)) {
            throw new InvalidValueException('Paletted storage values do not match the required size.');
        }
        $palette = [];
        $indexByRuntimeId = [];
        $indices = [];
        foreach ($runtimeIds as $runtimeId) {
            if (!self::isRuntimeId($runtimeId)) {
                throw new InvalidValueException('Paletted storage contains an invalid runtime ID.');
            }
            if (!array_key_exists($runtimeId, $indexByRuntimeId)) {
                $indexByRuntimeId[$runtimeId] = count($palette);
                $palette[] = $runtimeId;
            }
            $indices[] = $indexByRuntimeId[$runtimeId];
        }
        return new self($palette, $indices);
    }

    public static function singleton(int $runtimeId, int $size, int $minimumBitsPerEntry = 0): self
    {
        if ($size < 1 || $size > self::MAX_ENTRIES) {
            throw new InvalidValueException('Paletted storage singleton size is invalid.');
        }
        return new self([$runtimeId], array_fill(0, $size, 0), $minimumBitsPerEntry);
    }

    public function size(): int
    {
        return count($this->indices);
    }

    public function valueAt(int $index): int
    {
        if ($index < 0 || $index >= count($this->indices)) {
            throw new InvalidValueException('Paletted storage cell index is outside its bounds.');
        }
        return $this->palette[$this->indices[$index]];
    }

    private static function isRuntimeId(mixed $runtimeId): bool
    {
        return is_int($runtimeId) && $runtimeId >= 0 && $runtimeId <= 0x7fffffff;
    }

    private static function isPaletteIndex(mixed $index, int $maximum): bool
    {
        return is_int($index) && $index >= 0 && $index <= $maximum;
    }
}
