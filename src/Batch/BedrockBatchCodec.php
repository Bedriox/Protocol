<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Batch;

use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class BedrockBatchCodec
{
    public const int GAME_PACKET_MARKER = 0xfe;

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

        return \chr(self::GAME_PACKET_MARKER) . $payload;
    }

    public static function decode(
        string $bytes,
        CompressionMode $mode,
        BatchLimits $limits,
        int $compressionThreshold = 1,
    ): BedrockBatch
    {
        if ($bytes !== '' && \strlen($bytes) - 1 > $limits->maximumInputBytes) {
            throw new BufferOverflowException('Bedrock batch envelope exceeds its configured input limit.');
        }
        if ($bytes === '' || \ord($bytes[0]) !== self::GAME_PACKET_MARKER) {
            throw new MalformedDataException('Bedrock batch game-packet marker is missing or invalid.');
        }

        $uncompressed = BatchCompressionCodec::decode(\substr($bytes, 1), $mode, $limits, $compressionThreshold);

        return new BedrockBatch(PacketBatchCodec::decode($uncompressed, $limits), $mode, $compressionThreshold);
    }
}
