<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

/** Composes Bedrock batch bodies with their stateful encryption directions. */
final class BedrockEncryptedBatchCodec
{
    private function __construct() {}

    public static function encode(string $clearBatch, BedrockEncryptor $encryptor): string
    {
        return $encryptor->encryptBatch($clearBatch);
    }

    public static function decode(string $encryptedBatch, BedrockDecryptor $decryptor): string
    {
        return $decryptor->decryptBatch($encryptedBatch);
    }
}
