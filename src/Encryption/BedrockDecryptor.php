<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

use LogicException;
use Throwable;
use Bedriox\Protocol\Batch\BedrockBatchCodec;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Exception\InvalidValueException;

/** One inbound Bedrock encryption direction. Integrity failure is terminal. */
final class BedrockDecryptor
{
    private const int TRAILER_BYTES = 8;

    private ?string $key;
    private ?Aes256CtrStream $stream;
    private ?PacketCounter $packetCounter;
    private readonly EncryptionLimits $limits;

    public function __construct(string $key, ?EncryptionLimits $limits = null)
    {
        if (strlen($key) !== 32) {
            throw new InvalidValueException('Bedrock encryption key must contain exactly 32 bytes.');
        }
        $this->key = $key;
        $this->stream = new Aes256CtrStream($key);
        $this->packetCounter = new PacketCounter();
        $this->limits = $limits ?? new EncryptionLimits();
    }

    public function __destruct()
    {
        $this->close();
    }

    /** Decrypts a complete Bedrock envelope whose 0xfe marker remains clear. */
    public function decryptEnvelope(string $encryptedEnvelope): string
    {
        $this->ensureOpen();
        if ($encryptedEnvelope === '' || \ord($encryptedEnvelope[0]) !== BedrockBatchCodec::GAME_PACKET_MARKER) {
            throw new MalformedDataException('Encrypted Bedrock envelope must begin with the clear 0xfe marker.');
        }
        if (\strlen($encryptedEnvelope) - 1 > $this->limits->maximumCompressedBatchBytes + self::TRAILER_BYTES) {
            $this->close();
            throw new MalformedDataException('Encrypted Bedrock envelope exceeds the encryption limit.');
        }
        $encryptedPayload = \substr($encryptedEnvelope, 1);

        return \chr(BedrockBatchCodec::GAME_PACKET_MARKER) . $this->decryptEncryptedBatch($encryptedPayload);
    }

    private function decryptEncryptedBatch(string $encryptedPayload): string
    {
        $this->ensureOpen();
        $length = strlen($encryptedPayload);
        if ($length < self::TRAILER_BYTES + 1 || $length > $this->limits->maximumCompressedBatchBytes + self::TRAILER_BYTES) {
            $this->close();
            throw new MalformedDataException('Encrypted payload length is outside the Bedrock limit.');
        }
        $key = $this->key;
        $stream = $this->stream;
        $counter = $this->packetCounter;
        if ($key === null || $stream === null || $counter === null) {
            throw new LogicException('Bedrock decryptor is closed.');
        }
        try {
            $counterBytes = $counter->consumeLittleEndian();
            $decrypted = $stream->update($encryptedPayload);
            $compressedBatch = substr($decrypted, 0, -self::TRAILER_BYTES);
            $actualTrailer = substr($decrypted, -self::TRAILER_BYTES);
            $expectedTrailer = substr(hash('sha256', $counterBytes . $compressedBatch . $key, true), 0, self::TRAILER_BYTES);
            if (!hash_equals($expectedTrailer, $actualTrailer)) {
                throw new MalformedDataException('Bedrock encryption trailer is invalid.');
            }
            return $compressedBatch;
        } catch (Throwable $exception) {
            $this->close();
            throw $exception;
        }
    }

    public function close(): void
    {
        $this->stream?->close();
        if ($this->key !== null && function_exists('sodium_memzero')) {
            sodium_memzero($this->key);
        }
        $this->key = null;
        $this->stream = null;
        $this->packetCounter = null;
    }

    private function ensureOpen(): void
    {
        if ($this->key === null) {
            throw new LogicException('Bedrock decryptor is closed.');
        }
    }
}
