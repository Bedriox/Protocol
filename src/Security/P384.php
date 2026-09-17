<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use OpenSSLAsymmetricKey;
use Bedriox\Protocol\Exception\CryptographicException;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class P384
{
    public static function importPublicDerBase64(string $encoded, SecurityLimits $limits = new SecurityLimits()) : OpenSSLAsymmetricKey
    {
        if ($encoded === '' || strlen($encoded) > (($limits->maximumSpkiDerBytes + 2) * 4)) {
            throw new MalformedDataException('SPKI value is empty or oversized');
        }
        $der = base64_decode($encoded, true);
        if ($der === false || strlen($der) > $limits->maximumSpkiDerBytes || base64_encode($der) !== $encoded) {
            throw new MalformedDataException('SPKI is not canonical standard base64');
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = @openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new MalformedDataException('SPKI is not a supported public key');
        }
        self::assertPublicKey($key);

        return $key;
    }

    public static function exportPublicDerBase64(OpenSSLAsymmetricKey $key, SecurityLimits $limits = new SecurityLimits()) : string
    {
        self::assertPublicKey($key);
        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['key']) || !is_string($details['key'])) {
            throw new CryptographicException('OpenSSL could not export the public key');
        }
        $body = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $details['key']);
        if (!is_string($body)) {
            throw new CryptographicException('OpenSSL returned an invalid public key');
        }
        $der = base64_decode($body, true);
        if ($der === false || strlen($der) > $limits->maximumSpkiDerBytes) {
            throw new CryptographicException('Exported public key exceeds its limit');
        }

        return base64_encode($der);
    }

    public static function assertPublicKey(OpenSSLAsymmetricKey $key) : void
    {
        self::assertKey($key, false);
    }

    public static function assertPrivateKey(OpenSSLAsymmetricKey $key) : void
    {
        self::assertKey($key, true);
    }

    public static function assertKeyPair(OpenSSLAsymmetricKey $privateKey, OpenSSLAsymmetricKey $publicKey) : void
    {
        self::assertPrivateKey($privateKey);
        self::assertPublicKey($publicKey);
        $privateCoordinates = self::coordinates($privateKey);
        $publicCoordinates = self::coordinates($publicKey);
        if (!hash_equals($privateCoordinates, $publicCoordinates)) {
            throw new InvalidValueException('P-384 public key does not match the private key');
        }
    }

    public static function sign(string $message, OpenSSLAsymmetricKey $privateKey) : string
    {
        self::assertPrivateKey($privateKey);
        $der = '';
        if (!openssl_sign($message, $der, $privateKey, OPENSSL_ALGO_SHA384) || !is_string($der)) {
            throw new CryptographicException('OpenSSL could not create an ES384 signature');
        }

        return JoseSignature::derToRaw($der);
    }

    public static function verify(string $message, string $rawSignature, OpenSSLAsymmetricKey $publicKey) : bool
    {
        self::assertPublicKey($publicKey);
        $result = openssl_verify($message, JoseSignature::rawToDer($rawSignature), $publicKey, OPENSSL_ALGO_SHA384);
        if ($result === -1) {
            throw new CryptographicException('OpenSSL could not verify the ES384 signature');
        }

        return $result === 1;
    }

    public static function deriveSharedSecret(OpenSSLAsymmetricKey $privateKey, OpenSSLAsymmetricKey $peerPublicKey) : string
    {
        self::assertPrivateKey($privateKey);
        self::assertPublicKey($peerPublicKey);
        $secret = openssl_pkey_derive($peerPublicKey, $privateKey);
        if ($secret === false || strlen($secret) !== 48) {
            throw new CryptographicException('OpenSSL could not derive a P-384 shared secret');
        }

        return $secret;
    }

    public static function deriveSessionKey(string $salt, string $sharedSecret) : string
    {
        if (strlen($salt) !== 16 || strlen($sharedSecret) !== 48) {
            throw new InvalidValueException('Session derivation requires a 16-byte salt and 48-byte P-384 secret');
        }

        return hash('sha256', $salt . $sharedSecret, true);
    }

    private static function assertKey(OpenSSLAsymmetricKey $key, bool $requirePrivate) : void
    {
        $details = openssl_pkey_get_details($key);
        $ec = $details === false ? null : ($details['ec'] ?? null);
        $curve = is_array($ec) ? ($ec['curve_name'] ?? null) : null;
        $hasPrivate = is_array($ec) && isset($ec['d']);
        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['bits'] ?? null) !== 384
            || !is_string($curve) || !in_array($curve, ['secp384r1', 'P-384'], true) || ($requirePrivate && !$hasPrivate)) {
            throw new InvalidValueException('Key must be a P-384 ' . ($requirePrivate ? 'private' : 'public') . ' key');
        }
    }

    private static function coordinates(OpenSSLAsymmetricKey $key) : string
    {
        $details = openssl_pkey_get_details($key);
        $ec = $details === false ? null : ($details['ec'] ?? null);
        $x = is_array($ec) ? ($ec['x'] ?? null) : null;
        $y = is_array($ec) ? ($ec['y'] ?? null) : null;
        if (!is_string($x) || $x === '' || strlen($x) > 48 || !is_string($y) || $y === '' || strlen($y) > 48) {
            throw new InvalidValueException('P-384 key lacks canonical public coordinates');
        }

        return str_pad($x, 48, "\0", STR_PAD_LEFT) . str_pad($y, 48, "\0", STR_PAD_LEFT);
    }
}
