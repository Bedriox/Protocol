<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Security\P384;
use Bedriox\Protocol\Security\SecurityLimits;
use OpenSSLAsymmetricKey;

/** Immutable identity assertion carried from Bedriox Gateway to a trusted backend. */
final readonly class GatewayForwardingClaims
{
    public function __construct(
        public string $issuer,
        public string $audience,
        public string $subject,
        public string $identity,
        public string $displayName,
        public string $xuid,
        public string $remoteAddress,
        public int $remotePort,
        public int $protocolVersion,
        public string $clientPublicKey,
        public int $issuedAt,
        public int $notBefore,
        public int $expiresAt,
        public string $tokenId,
        public ?string $minecraftId = null,
        public ?int $runtimeEntityId = null,
        public bool $operator = false,
    ) {
        foreach ([
            'issuer' => [$issuer, 128],
            'audience' => [$audience, 128],
            'subject' => [$subject, 512],
            'identity' => [$identity, 64],
            'display name' => [$displayName, 64],
            'XUID' => [$xuid, 32],
            'token ID' => [$tokenId, 128],
        ] as $field => [$value, $maximum]) {
            if ($value === '' || strlen($value) > $maximum || preg_match('//u', $value) !== 1) {
                throw new InvalidValueException("Gateway forwarding {$field} is invalid.");
            }
        }
        if (preg_match('/\A[0-9]+\z/D', $xuid) !== 1) {
            throw new InvalidValueException('Gateway forwarding XUID must be decimal.');
        }
        if (filter_var($remoteAddress, FILTER_VALIDATE_IP) === false || strlen($remoteAddress) > 45) {
            throw new InvalidValueException('Gateway forwarding remote address is invalid.');
        }
        if ($remotePort < 1 || $remotePort > 65_535 || $protocolVersion < 1) {
            throw new InvalidValueException('Gateway forwarding port or protocol version is invalid.');
        }
        if ($issuedAt < 0 || $notBefore < 0 || $expiresAt <= $notBefore || $issuedAt > $expiresAt) {
            throw new InvalidValueException('Gateway forwarding time window is invalid.');
        }
        if ($minecraftId !== null && ($minecraftId === '' || strlen($minecraftId) > 128 || preg_match('//u', $minecraftId) !== 1)) {
            throw new InvalidValueException('Gateway forwarding Minecraft ID is invalid.');
        }
        if ($runtimeEntityId !== null && ($runtimeEntityId < 1 || $runtimeEntityId > 0x7fffffff)) {
            throw new InvalidValueException('Gateway forwarding runtime entity ID is invalid.');
        }
        P384::importPublicDerBase64($clientPublicKey, new SecurityLimits(maximumSpkiDerBytes: 512));
    }

    /** @return array<string, int|string|bool> */
    public function toPayload(): array
    {
        $payload = [
            'v' => 1,
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => $this->subject,
            'identity' => $this->identity,
            'xname' => $this->displayName,
            'xid' => $this->xuid,
            'ip' => $this->remoteAddress,
            'port' => $this->remotePort,
            'protocol' => $this->protocolVersion,
            'cpk' => $this->clientPublicKey,
            'iat' => $this->issuedAt,
            'nbf' => $this->notBefore,
            'exp' => $this->expiresAt,
            'jti' => $this->tokenId,
        ];
        if ($this->minecraftId !== null) {
            $payload['mid'] = $this->minecraftId;
        }
        if ($this->runtimeEntityId !== null) {
            $payload['rid'] = $this->runtimeEntityId;
        }
        if ($this->operator) {
            $payload['op'] = true;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (($payload['v'] ?? null) !== 1) {
            throw new InvalidValueException('Gateway forwarding version is unsupported.');
        }
        $string = static function (string $name) use ($payload): string {
            $value = $payload[$name] ?? null;
            if (!is_string($value)) {
                throw new InvalidValueException("Gateway forwarding {$name} claim is invalid.");
            }

            return $value;
        };
        $integer = static function (string $name) use ($payload): int {
            $value = $payload[$name] ?? null;
            if (!is_int($value)) {
                throw new InvalidValueException("Gateway forwarding {$name} claim is invalid.");
            }

            return $value;
        };
        $minecraftId = $payload['mid'] ?? null;
        if ($minecraftId !== null && !is_string($minecraftId)) {
            throw new InvalidValueException('Gateway forwarding mid claim is invalid.');
        }
        $runtimeEntityId = $payload['rid'] ?? null;
        if ($runtimeEntityId !== null && !is_int($runtimeEntityId)) {
            throw new InvalidValueException('Gateway forwarding rid claim is invalid.');
        }
        $operator = $payload['op'] ?? false;
        if (!is_bool($operator)) {
            throw new InvalidValueException('Gateway forwarding op claim is invalid.');
        }

        return new self(
            $string('iss'),
            $string('aud'),
            $string('sub'),
            $string('identity'),
            $string('xname'),
            $string('xid'),
            $string('ip'),
            $integer('port'),
            $integer('protocol'),
            $string('cpk'),
            $integer('iat'),
            $integer('nbf'),
            $integer('exp'),
            $string('jti'),
            $minecraftId,
            $runtimeEntityId,
            $operator,
        );
    }

    public function publicKey(): OpenSSLAsymmetricKey
    {
        return P384::importPublicDerBase64($this->clientPublicKey, new SecurityLimits(maximumSpkiDerBytes: 512));
    }
}
