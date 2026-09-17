<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

use OpenSSLAsymmetricKey;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Security\BoundedJson;
use Bedriox\Protocol\Security\CompactJws;
use Bedriox\Protocol\Security\P384;

final readonly class LegacyCertificateChainVerifier
{
    private ?string $pinnedRoot;
    private IdentityProofLimits $limits;

    public function __construct(
        ?OpenSSLAsymmetricKey $pinnedRoot,
        ?IdentityProofLimits $limits = null,
    ) {
        if ($pinnedRoot !== null) {
            P384::assertPublicKey($pinnedRoot);
        }
        $this->limits = $limits ?? new IdentityProofLimits();
        $this->pinnedRoot = $pinnedRoot === null
            ? null
            : P384::exportPublicDerBase64($pinnedRoot, $this->limits->jws);
    }

    public static function forExplicitSelfSigned(?IdentityProofLimits $limits = null): self
    {
        return new self(null, $limits);
    }

    public function verify(string $certificateJson, CertificateChainMode $mode, int $nowEpochSeconds): VerifiedIdentity
    {
        $pinnedRoot = $this->pinnedRoot;
        if ($mode === CertificateChainMode::OnlineLegacy && $pinnedRoot === null) {
            throw new InvalidValueException('Online legacy verification requires a pinned root.');
        }
        if ($nowEpochSeconds < 0) {
            throw new InvalidValueException('Current epoch time cannot be negative.');
        }
        if (strlen($certificateJson) > $this->limits->maximumCertificateJsonBytes) {
            throw new MalformedDataException('Certificate JSON exceeds its byte limit.');
        }
        JsonTokenCounter::assertWithin($certificateJson, $this->limits->maximumCertificateJsonTokens);
        $object = BoundedJson::decodeObject(
            $certificateJson,
            $this->limits->maximumCertificateJsonBytes,
            $this->limits->jws->maximumJsonDepth,
        );
        $chain = $object['chain'] ?? null;
        $expectedCount = $mode === CertificateChainMode::OnlineLegacy ? 3 : 1;
        if (!is_array($chain) || !array_is_list($chain) || count($chain) !== $expectedCount) {
            throw new MalformedDataException('Certificate chain has the wrong token count for its explicit mode.');
        }

        $expectedHeaderKey = null;
        $finalPayload = null;
        $finalKey = null;
        foreach ($chain as $position => $compact) {
            if (!is_string($compact)) {
                throw new MalformedDataException('Certificate chain entries must be compact JWS strings.');
            }
            $jws = CompactJws::parse($compact, $this->limits->jws);
            $headerKey = $jws->header['x5u'] ?? null;
            if (!is_string($headerKey)) {
                throw new MalformedDataException('Certificate JWS requires a canonical x5u SPKI.');
            }
            $publicKey = $this->importPresentedKey($headerKey);
            $canonicalHeaderKey = P384::exportPublicDerBase64($publicKey, $this->limits->jws);
            if ($expectedHeaderKey !== null && !hash_equals($expectedHeaderKey, $canonicalHeaderKey)) {
                throw new MalformedDataException('Certificate identityPublicKey link does not match the next x5u.');
            }
            if ($mode === CertificateChainMode::OnlineLegacy && $position === 1
                && ($pinnedRoot === null || !hash_equals($pinnedRoot, $canonicalHeaderKey))) {
                throw new MalformedDataException('Certificate chain does not contain the pinned root at its required position.');
            }
            if (!CompactJws::verify($jws, $publicKey)) {
                throw new MalformedDataException('Certificate JWS signature is invalid.');
            }
            self::validateTimeClaims($jws->payload, $nowEpochSeconds);
            $identityPublicKey = $jws->payload['identityPublicKey'] ?? null;
            if (!is_string($identityPublicKey)) {
                throw new MalformedDataException('Certificate payload lacks identityPublicKey.');
            }
            $nextKey = $this->importPresentedKey($identityPublicKey);
            $expectedHeaderKey = P384::exportPublicDerBase64($nextKey, $this->limits->jws);
            $finalPayload = $jws->payload;
            $finalKey = $nextKey;
        }
        $extraData = $finalPayload['extraData'] ?? null;
        if (!is_array($extraData)) {
            throw new MalformedDataException('Final certificate lacks extraData.');
        }
        $displayName = self::requiredString($extraData, 'displayName', $this->limits->maximumDisplayNameBytes);
        $identity = self::requiredString($extraData, 'identity', 36);
        $xuid = $extraData['XUID'] ?? null;
        if (!is_string($xuid) || strlen($xuid) > $this->limits->maximumXuidBytes
            || ($mode === CertificateChainMode::OnlineLegacy && $xuid === '')) {
            throw new MalformedDataException('Identity XUID claim is missing or invalid.');
        }
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/D', $identity) !== 1) {
            throw new MalformedDataException('Identity claim must be a canonical UUID.');
        }
        if ($xuid !== '' && preg_match('/^[0-9]+$/D', $xuid) !== 1) {
            throw new MalformedDataException('XUID claim must contain decimal digits only.');
        }
        return new VerifiedIdentity($displayName, strtolower($identity), $xuid, $finalKey, $mode);
    }

    /** @param array<string, mixed> $payload */
    private static function validateTimeClaims(array $payload, int $now): void
    {
        foreach (['nbf', 'exp'] as $name) {
            if (array_key_exists($name, $payload) && !is_int($payload[$name])) {
                throw new MalformedDataException("Certificate {$name} claim must be an integer.");
            }
        }
        if (array_key_exists('nbf', $payload) && $payload['nbf'] > $now) {
            throw new MalformedDataException('Certificate is not valid yet.');
        }
        if (array_key_exists('exp', $payload) && $payload['exp'] <= $now) {
            throw new MalformedDataException('Certificate is expired.');
        }
    }

    /** @param array<array-key, mixed> $object */
    private static function requiredString(array $object, string $name, int $maximumBytes): string
    {
        $value = $object[$name] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $maximumBytes || preg_match('//u', $value) !== 1) {
            throw new MalformedDataException("Identity {$name} claim is missing or invalid.");
        }
        return $value;
    }

    private function importPresentedKey(string $encoded): OpenSSLAsymmetricKey
    {
        try {
            return P384::importPublicDerBase64($encoded, $this->limits->jws);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException('Certificate SPKI is not a P-384 public key.', previous: $exception);
        }
    }
}
