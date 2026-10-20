<?php

declare(strict_types=1);

namespace Bedriox\Protocol\GatewayControl;

use Bedriox\Protocol\Exception\CryptographicException;
use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ControlFrameCipher
{
    private const int TAG_BYTES = 16;

    public function __construct(
        #[\SensitiveParameter] private string $key,
        private string $direction,
    ) {
        if (strlen($key) !== 32) {
            throw new InvalidValueException('Gateway control session key must contain 32 bytes.');
        }
        if (!in_array($direction, ['gateway-to-server', 'server-to-gateway'], true)) {
            throw new InvalidValueException('Gateway control cipher direction is invalid.');
        }
    }

    public function seal(ControlFrame $frame): ControlFrame
    {
        if ($frame->encrypted()) {
            throw new InvalidValueException('Gateway control frame is already encrypted.');
        }
        if (strlen($frame->payload) > ControlFrame::MAXIMUM_PAYLOAD_BYTES - self::TAG_BYTES) {
            throw new InvalidValueException('Gateway control plaintext leaves no room for its authentication tag.');
        }
        $tag = '';
        $ciphertext = openssl_encrypt(
            $frame->payload,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $this->nonce($frame),
            $tag,
            $this->additionalData($frame),
            self::TAG_BYTES,
        );
        if (!is_string($ciphertext) || strlen($tag) !== self::TAG_BYTES) {
            throw new CryptographicException('Gateway control payload encryption failed.');
        }

        return new ControlFrame(
            $frame->type,
            $frame->requestId,
            $frame->sequence,
            $ciphertext . $tag,
            $frame->flags | ControlFrame::FLAG_ENCRYPTED,
        );
    }

    public function open(ControlFrame $frame): ControlFrame
    {
        if (!$frame->encrypted() || strlen($frame->payload) < self::TAG_BYTES) {
            throw new CryptographicException('Gateway control frame is not a valid encrypted frame.');
        }
        $ciphertext = substr($frame->payload, 0, -self::TAG_BYTES);
        $tag = substr($frame->payload, -self::TAG_BYTES);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $this->nonce($frame),
            $tag,
            $this->additionalData($frame),
        );
        if (!is_string($plaintext)) {
            throw new CryptographicException('Gateway control payload authentication failed.');
        }

        return new ControlFrame(
            $frame->type,
            $frame->requestId,
            $frame->sequence,
            $plaintext,
        );
    }

    private function nonce(ControlFrame $frame): string
    {
        return substr(hash_hmac('sha256', "nonce\0{$this->direction}", $this->key, true), 0, 8)
            . pack('N', $frame->sequence);
    }

    private function additionalData(ControlFrame $frame): string
    {
        $requestId = hex2bin($frame->requestId);
        if (!is_string($requestId)) {
            throw new \LogicException('Validated Gateway control request ID could not be encoded.');
        }

        return ControlFrameCodec::MAGIC
            . chr(ControlFrame::VERSION)
            . chr($frame->type->value)
            . $requestId
            . pack('N', $frame->sequence)
            . $this->direction;
    }
}
