<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Batch;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Independent hostile-input limits for one Bedrock game-packet batch. */
final readonly class BatchLimits
{
    public function __construct(
        public int $maximumInputBytes = 1_048_576,
        public int $maximumDecompressedBytes = 4_194_304,
        public int $maximumCompressionRatio = 128,
        public int $maximumPackets = 512,
        public int $maximumPacketBytes = 1_048_576,
    ) {
        foreach (
            [
                $maximumInputBytes,
                $maximumDecompressedBytes,
                $maximumCompressionRatio,
                $maximumPackets,
                $maximumPacketBytes,
            ] as $limit
        ) {
            if ($limit < 1) {
                throw new InvalidValueException('Batch limits must be positive.');
            }
        }
        if ($maximumPacketBytes > $maximumDecompressedBytes) {
            throw new InvalidValueException('Per-packet limit cannot exceed the decompressed batch limit.');
        }
    }
}
