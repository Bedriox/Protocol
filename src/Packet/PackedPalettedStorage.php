<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Immutable, pre-packed Bedrock palette storage in X-Z-Y cell order. */
final readonly class PackedPalettedStorage
{
    private const array BITS_PER_ENTRY = [1, 2, 3, 4, 5, 6, 8, 16];

    /**
     * @param list<int> $palette Runtime IDs in stable palette order.
     */
    public function __construct(
        public array $palette,
        public int $bitsPerEntry,
        public string $wordArray,
        public int $entryCount,
    ) {
        if (!array_is_list($palette) || $palette === [] || count($palette) > PalettedStorage::MAX_PALETTE_SIZE
            || count(array_unique($palette, SORT_REGULAR)) !== count($palette)) {
            throw new InvalidValueException('Packed paletted storage palette must be a non-empty bounded unique list.');
        }
        foreach ($palette as $runtimeId) {
            if (!is_int($runtimeId) || $runtimeId < 0 || $runtimeId > 0x7fff_ffff) {
                throw new InvalidValueException('Packed paletted storage runtime ID is outside the signed-varint range.');
            }
        }
        if ($entryCount < 1 || $entryCount > PalettedStorage::MAX_ENTRIES) {
            throw new InvalidValueException('Packed paletted storage entry count is outside its bound.');
        }

        $paletteSize = count($palette);
        if ($bitsPerEntry === 0) {
            if ($paletteSize !== 1 || $wordArray !== '') {
                throw new InvalidValueException('Zero-bit packed storage must contain one palette entry and no words.');
            }

            return;
        }
        if (!in_array($bitsPerEntry, self::BITS_PER_ENTRY, true)
            || $paletteSize > (1 << $bitsPerEntry)) {
            throw new InvalidValueException('Packed paletted storage bit width cannot represent its palette.');
        }
        $entriesPerWord = intdiv(32, $bitsPerEntry);
        $wordCount = intdiv($entryCount + $entriesPerWord - 1, $entriesPerWord);
        if (strlen($wordArray) !== $wordCount * 4) {
            throw new InvalidValueException('Packed paletted storage word-array length is invalid.');
        }

        $indexMask = (1 << $bitsPerEntry) - 1;
        $fullWordMask = (1 << ($entriesPerWord * $bitsPerEntry)) - 1;
        $needsIndexValidation = $paletteSize !== (1 << $bitsPerEntry);
        for ($wordIndex = 0; $wordIndex < $wordCount; ++$wordIndex) {
            $decoded = unpack('Vvalue', substr($wordArray, $wordIndex * 4, 4));
            $word = is_array($decoded) ? ($decoded['value'] ?? null) : null;
            if (!is_int($word) || ($word & ~$fullWordMask) !== 0) {
                throw new InvalidValueException('Packed paletted storage contains non-canonical word padding.');
            }
            $entriesInWord = min($entriesPerWord, $entryCount - ($wordIndex * $entriesPerWord));
            if ($entriesInWord < $entriesPerWord) {
                $usedMask = (1 << ($entriesInWord * $bitsPerEntry)) - 1;
                if (($word & ~$usedMask) !== 0) {
                    throw new InvalidValueException('Packed paletted storage contains non-zero trailing entries.');
                }
            }
            if (!$needsIndexValidation) {
                continue;
            }
            for ($entry = 0; $entry < $entriesInWord; ++$entry) {
                if ((($word >> ($entry * $bitsPerEntry)) & $indexMask) >= $paletteSize) {
                    throw new InvalidValueException('Packed paletted storage index refers outside its palette.');
                }
            }
        }
    }

    public function size(): int
    {
        return $this->entryCount;
    }
}
