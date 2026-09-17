<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

use LogicException;
use Throwable;
use Bedriox\Protocol\Batch\BedrockBatchCodec;
use Bedriox\Protocol\Exception\InvalidValueException;

/** One outbound Bedrock encryption direction. */
final class BedrockEncryptor
{
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

    /** Encrypts a complete clear Bedrock envelope while leaving its 0xfe marker clear. */
    public function encryptEnvelope(string $clearEnvelope): string
    {
        $this->ensureOpen();
        if ($clearEnvelope === '' || \ord($clearEnvelope[0]) !== BedrockBatchCodec::GAME_PACKET_MARKER) {
            throw new InvalidValueException('Clear Bedrock envelope must begin with the 0xfe marker.');
        }
        if (\strlen($clearEnvelope) - 1 > $this->limits->maximumCompressedBatchBytes) {
            throw new InvalidValueException('Clear Bedrock envelope exceeds the encryption limit.');
        }
        $compressedBatch = \substr($clearEnvelope, 1);
        if ($compressedBatch === '') {
            throw new InvalidValueException('Clear Bedrock envelope must contain a compressed batch.');
        }

        return \chr(BedrockBatchCodec::GAME_PACKET_MARKER) . $this->encryptCompressedBatch($compressedBatch);
    }

    private function encryptCompressedBatch(string $compressedBatch): string
    {
        $this->ensureOpen();
        $length = strlen($compressedBatch);
        if ($length < 1 || $length > $this->limits->maximumCompressedBatchBytes) {
            throw new InvalidValueException('Compressed batch length is outside the encryption limit.');
        }
        $key = $this->key;
        $stream = $this->stream;
        $counter = $this->packetCounter;
        if ($key === null || $stream === null || $counter === null) {
            throw new LogicException('Bedrock encryptor is closed.');
        }
        try {
            $counterBytes = $counter->consumeLittleEndian();
            $trailer = substr(hash('sha256', $counterBytes . $compressedBatch . $key, true), 0, 8);
            return $stream->update($compressedBatch . $trailer);
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
            throw new LogicException('Bedrock encryptor is closed.');
        }
    }
}
