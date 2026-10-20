<?php

declare(strict_types=1);

namespace Bedriox\Protocol\GatewayControl;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ControlFrame
{
    public const int VERSION = 1;
    public const int FLAG_ENCRYPTED = 0x01;
    public const int MAXIMUM_PAYLOAD_BYTES = 65_536;

    public function __construct(
        public ControlMessageType $type,
        public string $requestId,
        public int $sequence,
        public string $payload,
        public int $flags = 0,
    ) {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $requestId) !== 1) {
            throw new InvalidValueException('Gateway control request ID must be 16 canonical hexadecimal bytes.');
        }
        if ($sequence < 0 || $sequence > 0xffffffff) {
            throw new InvalidValueException('Gateway control sequence is outside its unsigned 32-bit range.');
        }
        if (($flags & ~self::FLAG_ENCRYPTED) !== 0) {
            throw new InvalidValueException('Gateway control frame contains unsupported flags.');
        }
        if (strlen($payload) > self::MAXIMUM_PAYLOAD_BYTES) {
            throw new InvalidValueException('Gateway control payload exceeds its byte limit.');
        }
    }

    public static function requestId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function encrypted(): bool
    {
        return ($this->flags & self::FLAG_ENCRYPTED) !== 0;
    }
}
