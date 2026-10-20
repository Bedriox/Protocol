<?php

declare(strict_types=1);

namespace Bedriox\Protocol\GatewayControl;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Security\Base64Url;

final readonly class ControlHandshakeClaims
{
    public function __construct(
        public ControlHandshakeStage $stage,
        public string $issuer,
        public string $audience,
        public string $keyId,
        public string $gatewayNonce,
        public ?string $serverNonce,
        public int $issuedAt,
        public int $expiresAt,
    ) {
        foreach (['issuer' => $issuer, 'audience' => $audience, 'key ID' => $keyId] as $name => $value) {
            if ($value === '' || strlen($value) > 128 || preg_match('/\A[\x21-\x7e]+\z/D', $value) !== 1) {
                throw new InvalidValueException("Gateway control {$name} is invalid.");
            }
        }
        self::validateNonce($gatewayNonce, 'gateway');
        if ($stage === ControlHandshakeStage::Hello && $serverNonce !== null) {
            throw new InvalidValueException('Gateway control hello may not contain a server nonce.');
        }
        if ($stage !== ControlHandshakeStage::Hello && $serverNonce === null) {
            throw new InvalidValueException('Gateway control challenge and authentication require a server nonce.');
        }
        if ($serverNonce !== null) {
            self::validateNonce($serverNonce, 'server');
        }
        if ($issuedAt < 0 || $expiresAt <= $issuedAt || $expiresAt - $issuedAt > 60) {
            throw new InvalidValueException('Gateway control handshake time window is invalid.');
        }
    }

    public static function nonce(): string
    {
        return Base64Url::encode(random_bytes(32));
    }

    /** @return array{v: int, stage: string, iss: string, aud: string, kid: string, gn: string, iat: int, exp: int, sn?: string} */
    public function toPayload(): array
    {
        $payload = [
            'v' => 1,
            'stage' => $this->stage->value,
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'kid' => $this->keyId,
            'gn' => $this->gatewayNonce,
            'iat' => $this->issuedAt,
            'exp' => $this->expiresAt,
        ];
        if ($this->serverNonce !== null) {
            $payload['sn'] = $this->serverNonce;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (($payload['v'] ?? null) !== 1) {
            throw new InvalidValueException('Gateway control handshake version is unsupported.');
        }
        $string = static function (string $name) use ($payload): string {
            $value = $payload[$name] ?? null;
            if (!is_string($value)) {
                throw new InvalidValueException("Gateway control {$name} claim is invalid.");
            }

            return $value;
        };
        $integer = static function (string $name) use ($payload): int {
            $value = $payload[$name] ?? null;
            if (!is_int($value)) {
                throw new InvalidValueException("Gateway control {$name} claim is invalid.");
            }

            return $value;
        };
        $stage = ControlHandshakeStage::tryFrom($string('stage'));
        if ($stage === null) {
            throw new InvalidValueException('Gateway control handshake stage is unsupported.');
        }
        $serverNonce = $payload['sn'] ?? null;
        if ($serverNonce !== null && !is_string($serverNonce)) {
            throw new InvalidValueException('Gateway control server nonce claim is invalid.');
        }

        return new self(
            $stage,
            $string('iss'),
            $string('aud'),
            $string('kid'),
            $string('gn'),
            $serverNonce,
            $integer('iat'),
            $integer('exp'),
        );
    }

    private static function validateNonce(string $nonce, string $name): void
    {
        try {
            $decoded = Base64Url::decode($nonce, 32);
        } catch (\Throwable $exception) {
            throw new InvalidValueException("Gateway control {$name} nonce is invalid.", previous: $exception);
        }
        if (strlen($decoded) !== 32) {
            throw new InvalidValueException("Gateway control {$name} nonce must contain 32 bytes.");
        }
    }
}
