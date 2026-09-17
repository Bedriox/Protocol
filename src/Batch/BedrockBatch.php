<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Batch;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Packet\PacketFrame;

final readonly class BedrockBatch
{
    /** @param list<PacketFrame> $packets */
    public function __construct(
        public array $packets,
        public CompressionMode $compressionMode,
        public int $compressionThreshold = 1,
    ) {
        if ($packets === []) {
            throw new InvalidValueException('A Bedrock batch must contain at least one packet.');
        }
        if ($compressionThreshold < 0 || $compressionThreshold > 0xffff) {
            throw new InvalidValueException('Compression threshold must fit in an unsigned short.');
        }
    }
}
