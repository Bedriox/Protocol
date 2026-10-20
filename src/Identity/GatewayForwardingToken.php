<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Security\Base64Url;
use Bedriox\Protocol\Security\BoundedJson;
use JsonException;

/** Bounded HMAC-SHA-256 forwarding token used only between Gateway and trusted Bedriox backends. */
final class GatewayForwardingToken
{
    public const int MINIMUM_SECRET_BYTES = 32;
    public const int MAXIMUM_TOKEN_BYTES = 8_192;

    public static function issue(GatewayForwardingClaims $claims, #[\SensitiveParameter] string $secret): string
    {
        self::validateSecret($secret);
        try {
            $json = json_encode($claims->toPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidValueException('Gateway forwarding claims cannot be encoded.', previous: $exception);
        }
        if (!is_string($json) || strlen($json) > 6_000) {
            throw new InvalidValueException('Gateway forwarding claims exceed their byte limit.');
        }
        $payload = Base64Url::encode($json);
        $token = $payload . '.' . Base64Url::encode(hash_hmac('sha256', $payload, $secret, true));
        if (strlen($token) > self::MAXIMUM_TOKEN_BYTES) {
            throw new InvalidValueException('Gateway forwarding token exceeds its byte limit.');
        }

        return $token;
    }

    public static function verify(string $token, #[\SensitiveParameter] string $secret): GatewayForwardingClaims
    {
        self::validateSecret($secret);
        if ($token === '' || strlen($token) > self::MAXIMUM_TOKEN_BYTES) {
            throw new MalformedDataException('Gateway forwarding token is empty or oversized.');
        }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new MalformedDataException('Gateway forwarding token must contain two segments.');
        }
        $signature = Base64Url::decode($parts[1], 32);
        if (strlen($signature) !== 32 || !hash_equals(hash_hmac('sha256', $parts[0], $secret, true), $signature)) {
            throw new MalformedDataException('Gateway forwarding token signature is invalid.');
        }
        $json = Base64Url::decode($parts[0], 6_000);
        $payload = BoundedJson::decodeObject($json, 6_000, 8);

        return GatewayForwardingClaims::fromPayload($payload);
    }

    private static function validateSecret(#[\SensitiveParameter] string $secret): void
    {
        if (strlen($secret) < self::MINIMUM_SECRET_BYTES || strlen($secret) > 1_024) {
            throw new InvalidValueException('Gateway forwarding secret must contain between 32 and 1024 bytes.');
        }
    }
}
