<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

use LogicException;
use OverflowException;
use Bedriox\Protocol\Exception\InvalidValueException;

/** @internal Continuous AES-256-CTR stream backed by OpenSSL's native bulk primitive. */
final class Aes256CtrStream
{
    private ?string $key;
    private ?string $counterBlock;
    private ?string $remainingKeystream = '';

    public function __construct(string $key)
    {
        if (strlen($key) !== 32) {
            throw new InvalidValueException('AES-256 key must contain exactly 32 bytes.');
        }
        $this->key = $key;
        $this->counterBlock = substr($key, 0, 12) . "\0\0\0\2";
    }

    public function __destruct()
    {
        $this->close();
    }

    public function update(string $input): string
    {
        $key = $this->key;
        if ($key === null || $this->counterBlock === null || $this->remainingKeystream === null) {
            throw new LogicException('AES-CTR stream is closed.');
        }
        $length = strlen($input);
        if ($length === 0) {
            return '';
        }

        $output = '';
        $offset = 0;
        $remainingKeystream = $this->remainingKeystream;
        if ($remainingKeystream !== '') {
            $take = min(strlen($remainingKeystream), $length - $offset);
            $output .= substr($input, $offset, $take) ^ substr($remainingKeystream, 0, $take);
            $this->remainingKeystream = substr($remainingKeystream, $take);
            $offset += $take;
            if ($offset === $length) {
                return $output;
            }
        }

        $counterBlock = $this->counterBlock;
        $remainingLength = $length - $offset;
        $padding = (16 - ($remainingLength % 16)) % 16;
        $paddedInput = substr($input, $offset) . str_repeat("\0", $padding);
        $encrypted = openssl_encrypt(
            $paddedInput,
            'aes-256-ctr',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $counterBlock,
        );
        if (!is_string($encrypted) || strlen($encrypted) !== strlen($paddedInput)) {
            $this->close();
            throw new EncryptionException('OpenSSL failed to update the AES-CTR stream.');
        }
        $output .= substr($encrypted, 0, $remainingLength);
        $this->remainingKeystream = $padding === 0 ? '' : substr($encrypted, $remainingLength);
        $this->incrementCounterBlocks(intdiv(strlen($paddedInput), 16));

        return $output;
    }

    public function close(): void
    {
        if ($this->key !== null && function_exists('sodium_memzero')) {
            sodium_memzero($this->key);
        }
        if ($this->counterBlock !== null && function_exists('sodium_memzero')) {
            sodium_memzero($this->counterBlock);
        }
        if ($this->remainingKeystream !== null && $this->remainingKeystream !== '' && function_exists('sodium_memzero')) {
            sodium_memzero($this->remainingKeystream);
        }
        $this->key = null;
        $this->counterBlock = null;
        $this->remainingKeystream = null;
    }

    private function incrementCounterBlocks(int $blocks): void
    {
        if ($this->counterBlock === null || $blocks < 1) {
            throw new LogicException('AES-CTR stream is closed.');
        }
        $carry = $blocks;
        for ($index = 15; $index >= 0; --$index) {
            $sum = ord($this->counterBlock[$index]) + ($carry & 0xff);
            $this->counterBlock[$index] = chr($sum & 0xff);
            $carry = intdiv($carry, 256) + intdiv($sum, 256);
            if ($carry === 0) {
                return;
            }
        }
        $this->close();
        throw new OverflowException('AES-CTR block counter overflowed.');
    }
}
