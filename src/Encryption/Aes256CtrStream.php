<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

use LogicException;
use OverflowException;
use Bedriox\Protocol\Exception\InvalidValueException;

/** @internal Continuous AES-256-CTR stream built from the AES-ECB block primitive. */
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
        $output = '';
        $offset = 0;
        $length = strlen($input);
        while ($offset < $length) {
            $remainingKeystream = $this->remainingKeystream;
            if ($remainingKeystream === '') {
                $counterBlock = $this->counterBlock;
                if ($counterBlock === null) {
                    throw new LogicException('AES-CTR stream is closed.');
                }
                $keystream = openssl_encrypt(
                    $counterBlock,
                    'aes-256-ecb',
                    $key,
                    OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                );
                if (!is_string($keystream) || strlen($keystream) !== 16) {
                    $this->close();
                    throw new EncryptionException('OpenSSL failed to generate AES-CTR keystream.');
                }
                $this->remainingKeystream = $keystream;
                $remainingKeystream = $keystream;
                $this->incrementCounterBlock();
            }
            $take = min(strlen($remainingKeystream), $length - $offset);
            $output .= substr($input, $offset, $take) ^ substr($remainingKeystream, 0, $take);
            $this->remainingKeystream = substr($remainingKeystream, $take);
            $offset += $take;
        }
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

    private function incrementCounterBlock(): void
    {
        if ($this->counterBlock === null) {
            throw new LogicException('AES-CTR stream is closed.');
        }
        for ($index = 15; $index >= 0; --$index) {
            $value = ord($this->counterBlock[$index]);
            if ($value !== 0xff) {
                $this->counterBlock[$index] = chr($value + 1);
                return;
            }
            $this->counterBlock[$index] = "\0";
        }
        $this->close();
        throw new OverflowException('AES-CTR block counter overflowed.');
    }
}
