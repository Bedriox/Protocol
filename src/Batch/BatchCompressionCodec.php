<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Batch;

use Bedriox\Protocol\Exception\BufferOverflowException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class BatchCompressionCodec
{
    private const int ALGORITHM_ZLIB = 0x00;
    private const int ALGORITHM_NONE = 0xff;
    /** Small chunks bound output produced by each native inflate call. */
    private const int INFLATE_INPUT_CHUNK_BYTES = 64;

    private function __construct() {}

    public static function encode(
        string $uncompressed,
        CompressionMode $mode,
        BatchLimits $limits,
        int $compressionThreshold = 1,
    ): string
    {
        self::requireThreshold($compressionThreshold);
        if (\strlen($uncompressed) > $limits->maximumDecompressedBytes) {
            throw new BufferOverflowException('Uncompressed batch exceeds its configured byte limit.');
        }

        $encoded = match ($mode) {
            CompressionMode::Uncompressed => $uncompressed,
            CompressionMode::Zlib => self::compressZlib($uncompressed, false),
            CompressionMode::PrefixedNone => \chr(self::ALGORITHM_NONE) . $uncompressed,
            CompressionMode::NegotiatedZlib => self::encodeNegotiatedZlib($uncompressed, $compressionThreshold),
        };
        if (\strlen($encoded) > $limits->maximumInputBytes) {
            throw new BufferOverflowException('Encoded batch exceeds its configured input limit.');
        }

        return $encoded;
    }

    public static function decode(
        string $encoded,
        CompressionMode $mode,
        BatchLimits $limits,
        int $compressionThreshold = 1,
    ): string
    {
        self::requireThreshold($compressionThreshold);
        if (\strlen($encoded) > $limits->maximumInputBytes) {
            throw new BufferOverflowException('Encoded batch exceeds its configured input limit.');
        }

        return match ($mode) {
            CompressionMode::Uncompressed => self::validatePlain($encoded, $limits),
            CompressionMode::Zlib => self::inflate($encoded, false, $limits),
            CompressionMode::PrefixedNone => self::decodePrefixedNone($encoded, $limits),
            CompressionMode::NegotiatedZlib => self::decodeNegotiatedZlib($encoded, $limits, $compressionThreshold),
        };
    }

    private static function encodeNegotiatedZlib(string $uncompressed, int $threshold): string
    {
        if (\strlen($uncompressed) < $threshold) {
            return \chr(self::ALGORITHM_NONE) . $uncompressed;
        }

        return \chr(self::ALGORITHM_ZLIB) . self::compressZlib($uncompressed, true);
    }

    private static function compressZlib(string $uncompressed, bool $raw): string
    {
        $compressed = $raw ? gzdeflate($uncompressed, 7) : gzcompress($uncompressed, 7);
        if ($compressed === false) {
            throw new MalformedDataException('Unable to compress the batch.');
        }

        return $compressed;
    }

    private static function validatePlain(string $bytes, BatchLimits $limits): string
    {
        if (\strlen($bytes) > $limits->maximumDecompressedBytes) {
            throw new BufferOverflowException('Plain batch exceeds its configured decompressed limit.');
        }

        return $bytes;
    }

    private static function decodePrefixedNone(string $encoded, BatchLimits $limits): string
    {
        self::requirePrefix($encoded, self::ALGORITHM_NONE);

        return self::validatePlain(\substr($encoded, 1), $limits);
    }

    private static function decodeNegotiatedZlib(string $encoded, BatchLimits $limits, int $threshold): string
    {
        if ($encoded === '') {
            throw new MalformedDataException('Compressed batch is missing its algorithm byte.');
        }
        $algorithm = \ord($encoded[0]);
        $body = \substr($encoded, 1);
        if ($algorithm === self::ALGORITHM_NONE) {
            $plain = self::validatePlain($body, $limits);
            if (\strlen($plain) >= $threshold) {
                throw new MalformedDataException('Uncompressed batch violates the negotiated zlib threshold.');
            }
            return $plain;
        }
        if ($algorithm !== self::ALGORITHM_ZLIB) {
            throw new MalformedDataException('Compressed batch algorithm byte is unsupported for negotiated zlib.');
        }

        $plain = self::inflate($body, true, $limits);
        if (\strlen($plain) < $threshold) {
            throw new MalformedDataException('Compressed batch violates the negotiated zlib threshold.');
        }
        return $plain;
    }

    private static function requireThreshold(int $threshold): void
    {
        if ($threshold < 0 || $threshold > 0xffff) {
            throw new \Bedriox\Protocol\Exception\InvalidValueException(
                'Compression threshold must fit in an unsigned short.',
            );
        }
    }

    private static function requirePrefix(string $encoded, int $expected): void
    {
        if ($encoded === '') {
            throw new MalformedDataException('Compressed batch is missing its algorithm byte.');
        }
        if (\ord($encoded[0]) !== $expected) {
            throw new MalformedDataException('Compressed batch algorithm byte is unexpected.');
        }
    }

    private static function inflate(string $compressed, bool $raw, BatchLimits $limits): string
    {
        if ($compressed === '') {
            throw new MalformedDataException('Compressed batch stream is empty.');
        }
        $context = inflate_init($raw ? ZLIB_ENCODING_RAW : ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new MalformedDataException('Unable to initialize batch decompression.');
        }

        $output = '';
        $inputBytes = \strlen($compressed);
        for ($offset = 0; $offset < $inputBytes; $offset += self::INFLATE_INPUT_CHUNK_BYTES) {
            $chunk = \substr($compressed, $offset, self::INFLATE_INPUT_CHUNK_BYTES);
            $finish = $offset + \strlen($chunk) >= $inputBytes;
            $inflated = @inflate_add($context, $chunk, $finish ? ZLIB_FINISH : ZLIB_SYNC_FLUSH);
            if ($inflated === false) {
                throw new MalformedDataException('Compressed batch stream is invalid or truncated.');
            }
            if (\strlen($inflated) > $limits->maximumDecompressedBytes - \strlen($output)) {
                throw new BufferOverflowException('Decompressed batch exceeds its configured byte limit.');
            }
            $output .= $inflated;
            self::requireRatio($output, $inputBytes, $limits->maximumCompressionRatio);
        }
        if (inflate_get_status($context) !== ZLIB_STREAM_END || inflate_get_read_len($context) !== $inputBytes) {
            throw new MalformedDataException('Compressed batch has trailing data or no complete stream terminator.');
        }

        return $output;
    }

    private static function requireRatio(string $output, int $inputBytes, int $maximumRatio): void
    {
        $outputBytes = \strlen($output);
        $actualRatioCeiling = intdiv($outputBytes - 1, $inputBytes) + 1;
        if ($outputBytes > 0 && $actualRatioCeiling > $maximumRatio) {
            throw new BufferOverflowException('Decompressed batch exceeds its configured compression ratio.');
        }
    }
}
