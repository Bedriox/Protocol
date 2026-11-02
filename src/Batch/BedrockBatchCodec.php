<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Batch;

use Bedriox\Protocol\Exception\BufferOverflowException;
final class BedrockBatchCodec
{
    private function __construct() {}

    public static function encode(BedrockBatch $batch, BatchLimits $limits): string
    {
        $uncompressed = PacketBatchCodec::encode($batch->packets, $limits);
        $payload = BatchCompressionCodec::encode(
            $uncompressed,
            $batch->compressionMode,
            $limits,
            $batch->compressionThreshold,
        );

        return $payload;
    }

    public static function decode(
        string $bytes,
        CompressionMode $mode,
        BatchLimits $limits,
        int $compressionThreshold = 1,
    ): BedrockBatch
    {
        if (\strlen($bytes) > $limits->maximumInputBytes) {
            throw new BufferOverflowException('Bedrock batch exceeds its configured input limit.');
        }

        $uncompressed = BatchCompressionCodec::decode($bytes, $mode, $limits, $compressionThreshold);

        return new BedrockBatch(PacketBatchCodec::decode($uncompressed, $limits), $mode, $compressionThreshold);
    }
}
