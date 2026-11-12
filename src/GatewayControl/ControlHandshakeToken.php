<?php

declare(strict_types=1);

namespace Bedriox\Protocol\GatewayControl;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Security\Base64Url;
use Bedriox\Protocol\Security\BoundedJson;
use JsonException;

final class ControlHandshakeToken
{
    public const int MINIMUM_SECRET_BYTES = 32;
    public const int MAXIMUM_TOKEN_BYTES = 2_048;

    public static function issue(ControlHandshakeClaims $claims, #[\SensitiveParameter] string $secret): string
    {
        self::validateSecret($secret);
        try {
            $json = json_encode($claims->toPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidValueException('Gateway control handshake claims cannot be encoded.', previous: $exception);
        }
        if (!is_string($json) || strlen($json) > 1_024) {
            throw new InvalidValueException('Gateway control handshake claims exceed their byte limit.');
        }
        $payload = Base64Url::encode($json);
        $token = $payload . '.' . Base64Url::encode(hash_hmac('sha256', $payload, $secret, true));
        if (strlen($token) > self::MAXIMUM_TOKEN_BYTES) {
            throw new InvalidValueException('Gateway control handshake token exceeds its byte limit.');
        }

        return $token;
    }

    public static function verify(string $token, #[\SensitiveParameter] string $secret): ControlHandshakeClaims
    {
        self::validateSecret($secret);
        if ($token === '' || strlen($token) > self::MAXIMUM_TOKEN_BYTES) {
            throw new MalformedDataException('Gateway control handshake token is empty or oversized.');
        }
        $parts = explode('.', $token, 3);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new MalformedDataException('Gateway control handshake token must contain two segments.');
        }
        $signature = Base64Url::decode($parts[1], 32);
        if (strlen($signature) !== 32 || !hash_equals(hash_hmac('sha256', $parts[0], $secret, true), $signature)) {
            throw new MalformedDataException('Gateway control handshake signature is invalid.');
        }

        return ControlHandshakeClaims::fromPayload(BoundedJson::decodeObject(
            Base64Url::decode($parts[0], 1_024),
            1_024,
            4,
        ));
    }

    public static function deriveSessionKey(
        #[\SensitiveParameter] string $secret,
        ControlHandshakeClaims $claims,
    ): string {
        self::validateSecret($secret);
        if ($claims->serverNonce === null) {
            throw new InvalidValueException('A server nonce is required to derive a Gateway control session key.');
        }
        $salt = hash('sha256', Base64Url::decode($claims->gatewayNonce, 32) . Base64Url::decode($claims->serverNonce, 32), true);
        $info = "bedriox-gateway-control-v1\0{$claims->issuer}\0{$claims->audience}\0{$claims->keyId}";

        return hash_hkdf('sha256', $secret, 32, $info, $salt);
    }

    private static function validateSecret(#[\SensitiveParameter] string $secret): void
    {
        if (strlen($secret) < self::MINIMUM_SECRET_BYTES || strlen($secret) > 1_024) {
            throw new InvalidValueException('Gateway control secret must contain between 32 and 1024 bytes.');
        }
    }
}
