<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use OpenSSLAsymmetricKey;
use Bedriox\Protocol\Exception\MalformedDataException;

final class CompactJws
{
    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    public static function sign(array $header, array $payload, OpenSSLAsymmetricKey $privateKey, SecurityLimits $limits = new SecurityLimits()) : string
    {
        if (($header['alg'] ?? null) !== 'ES384' || array_key_exists('crit', $header) || array_key_exists('b64', $header)) {
            throw new MalformedDataException('Unsupported JWS protected header');
        }
        $headerJson = self::encodeObject($header, $limits->maximumHeaderBytes, $limits->maximumJsonDepth);
        $payloadJson = self::encodeObject($payload, $limits->maximumPayloadBytes, $limits->maximumJsonDepth);
        $input = Base64Url::encode($headerJson) . '.' . Base64Url::encode($payloadJson);
        $compact = $input . '.' . Base64Url::encode(P384::sign($input, $privateKey));
        if (strlen($compact) > $limits->maximumCompactJwsBytes) {
            throw new MalformedDataException('Compact JWS exceeds its size limit');
        }

        return $compact;
    }

    public static function parse(string $compact, SecurityLimits $limits = new SecurityLimits()) : ParsedJws
    {
        if (strlen($compact) > $limits->maximumCompactJwsBytes) {
            throw new MalformedDataException('Compact JWS exceeds its size limit');
        }
        $parts = explode('.', $compact);
        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            throw new MalformedDataException('Compact JWS must have three non-empty segments');
        }
        $header = BoundedJson::decodeObject(Base64Url::decode($parts[0], $limits->maximumHeaderBytes), $limits->maximumHeaderBytes, $limits->maximumJsonDepth);
        $payload = BoundedJson::decodeObject(Base64Url::decode($parts[1], $limits->maximumPayloadBytes), $limits->maximumPayloadBytes, $limits->maximumJsonDepth);
        if (($header['alg'] ?? null) !== 'ES384' || array_key_exists('crit', $header) || array_key_exists('b64', $header)) {
            throw new MalformedDataException('Unsupported JWS protected header');
        }
        $signature = Base64Url::decode($parts[2], JoseSignature::RAW_BYTES);
        if (strlen($signature) !== JoseSignature::RAW_BYTES) {
            throw new MalformedDataException('ES384 JWS signature has the wrong length');
        }

        return new ParsedJws($header, $payload, $signature, $parts[0] . '.' . $parts[1]);
    }

    public static function verify(ParsedJws $jws, OpenSSLAsymmetricKey $publicKey) : bool
    {
        return P384::verify($jws->signingInput, $jws->signature, $publicKey);
    }

    /** @param array<string, mixed> $value */
    private static function encodeObject(array $value, int $maximumBytes, int $maximumDepth) : string
    {
        try {
            $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            throw new MalformedDataException('Value cannot be encoded as JSON');
        }
        if (array_is_list($value) || strlen($json) > $maximumBytes) {
            throw new MalformedDataException('JSON object exceeds its constraints');
        }
        BoundedJson::decodeObject($json, $maximumBytes, $maximumDepth);

        return $json;
    }
}
