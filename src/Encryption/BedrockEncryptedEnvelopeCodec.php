<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Encryption;

/** Composes the clear 0xfe Bedrock marker with Bedrock encrypted batch bytes. */
final class BedrockEncryptedEnvelopeCodec
{
    private function __construct() {}

    public static function encode(string $clearEnvelope, BedrockEncryptor $encryptor): string
    {
        return $encryptor->encryptEnvelope($clearEnvelope);
    }

    public static function decode(string $encryptedEnvelope, BedrockDecryptor $decryptor): string
    {
        return $decryptor->decryptEnvelope($encryptedEnvelope);
    }
}
