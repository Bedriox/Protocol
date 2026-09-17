<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Identity;

use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Identity\CertificateChainMode;
use Bedriox\Protocol\Identity\ClientDataJwtVerifier;
use Bedriox\Protocol\Identity\ClientDataLimits;
use Bedriox\Protocol\Identity\IdentityProofLimits;
use Bedriox\Protocol\Identity\LegacyCertificateChainVerifier;
use Bedriox\Protocol\Security\Base64Url;
use Bedriox\Protocol\Security\CompactJws;
use Bedriox\Protocol\Security\OpenSslEphemeralKeyFactory;
use Bedriox\Protocol\Security\P384;
use Bedriox\Protocol\Security\P384KeyPair;
use Bedriox\Protocol\Security\SecurityLimits;
use Bedriox\Protocol\Value\DeviceOs;

final class IdentityProofTest extends TestCase
{
    private const int NOW = 2_000_000_000;

    public function testOnlineThreeTokenChainAndClientDataVerify(): void
    {
        [$json, $root, $identityKey] = $this->onlineChain();
        $identity = (new LegacyCertificateChainVerifier($root->publicKey))->verify(
            $json,
            CertificateChainMode::OnlineLegacy,
            self::NOW,
        );
        self::assertSame('Veno Player', $identity->displayName);
        self::assertSame('123e4567-e89b-42d3-a456-426614174000', $identity->identity);
        self::assertSame('123456789', $identity->xuid);

        $clientJwt = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identityKey->publicKey)],
            self::validClientClaims(),
            $identityKey->privateKey,
        );
        $client = (new ClientDataJwtVerifier())->verify($clientJwt, $identity->identityPublicKey);
        self::assertSame(1, $client->skinWidth);
        self::assertSame("\x01\x02\x03\x04", $client->skin);
        self::assertSame('{}', $client->geometryJson);
        self::assertCount(1, $client->animations);
        self::assertSame('skin-id', $client->skinId);
        self::assertSame('{"geometry":{"default":"geometry.humanoid.custom"}}', $client->skinResourcePatchJson);
        self::assertSame('profile-hash', $client->profileHash);
        self::assertSame(DeviceOs::Windows, $client->deviceOs);
        self::assertTrue($client->primaryUser);
    }

    public function testExplicitSelfSignedAcceptsExactlyOneAndOnlineDoesNotFallback(): void
    {
        $signer = $this->generate();
        $identity = $this->generate();
        $token = $this->certificate($signer, $identity, true);
        $json = self::certificateJson([$token]);
        $verified = LegacyCertificateChainVerifier::forExplicitSelfSigned()->verify(
            $json,
            CertificateChainMode::SelfSignedExplicit,
            self::NOW,
        );
        self::assertSame(CertificateChainMode::SelfSignedExplicit, $verified->mode);

        $this->expectException(MalformedDataException::class);
        (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
            $json,
            CertificateChainMode::OnlineLegacy,
            self::NOW,
        );
    }

    public function testClientDataRejectsUndefinedDeviceOsZero(): void
    {
        $identity = $this->generate();
        $claims = self::validClientClaims();
        $claims['DeviceOS'] = 0;
        $jwt = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
            $claims,
            $identity->privateKey,
        );

        $this->expectException(MalformedDataException::class);
        (new ClientDataJwtVerifier())->verify($jwt, $identity->publicKey);
    }

    public function testRootlessVerifierFailsOnlineClosedBeforeParsingAndDoesNotFallback(): void
    {
        $verifier = LegacyCertificateChainVerifier::forExplicitSelfSigned();
        foreach (['not-json', self::certificateJson([$this->certificate($this->generate(), $this->generate(), true)])] as $input) {
            try {
                $verifier->verify($input, CertificateChainMode::OnlineLegacy, self::NOW);
                self::fail('Rootless verifier accepted online verification.');
            } catch (InvalidValueException $exception) {
                self::assertSame('Online legacy verification requires a pinned root.', $exception->getMessage());
            }
        }

        $signer = $this->generate();
        $identity = $this->generate();
        $verified = $verifier->verify(
            self::certificateJson([$this->certificate($signer, $identity, true)]),
            CertificateChainMode::SelfSignedExplicit,
            self::NOW,
        );
        self::assertSame(CertificateChainMode::SelfSignedExplicit, $verified->mode);
    }

    public function testSelfSignedAllowsEmptyXuidButOnlineRequiresIt(): void
    {
        $signer = $this->generate();
        $identity = $this->generate();
        $payload = self::identityPayload($identity);
        self::assertIsArray($payload['extraData']);
        $payload['extraData']['XUID'] = '';
        $selfToken = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($signer->publicKey)],
            $payload,
            $signer->privateKey,
        );
        $verified = (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
            self::certificateJson([$selfToken]),
            CertificateChainMode::SelfSignedExplicit,
            self::NOW,
        );
        self::assertSame('', $verified->xuid);

        $first = $this->generate();
        $root = $this->generate();
        $third = $this->generate();
        $onlineFinal = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($third->publicKey)],
            $payload,
            $third->privateKey,
        );
        $online = self::certificateJson([
            $this->certificate($first, $root),
            $this->certificate($root, $third),
            $onlineFinal,
        ]);
        $this->expectException(MalformedDataException::class);
        (new LegacyCertificateChainVerifier($root->publicKey))->verify($online, CertificateChainMode::OnlineLegacy, self::NOW);
    }

    public function testWrongPinnedRootIsRejected(): void
    {
        [$json] = $this->onlineChain();
        $this->expectException(MalformedDataException::class);
        (new LegacyCertificateChainVerifier($this->generate()->publicKey))->verify(
            $json,
            CertificateChainMode::OnlineLegacy,
            self::NOW,
        );
    }

    public function testBrokenIdentityKeyLinkIsRejected(): void
    {
        $first = $this->generate();
        $root = $this->generate();
        $third = $this->generate();
        $identity = $this->generate();
        $wrong = $this->generate();
        $json = self::certificateJson([
            $this->certificate($first, $wrong),
            $this->certificate($root, $third),
            $this->certificate($third, $identity, true),
        ]);
        $this->expectException(MalformedDataException::class);
        (new LegacyCertificateChainVerifier($root->publicKey))->verify($json, CertificateChainMode::OnlineLegacy, self::NOW);
    }

    public function testExpiredAndNotYetValidCertificatesAreRejected(): void
    {
        foreach ([['exp' => self::NOW], ['nbf' => self::NOW + 1]] as $timeClaims) {
            $signer = $this->generate();
            $identity = $this->generate();
            $token = $this->certificate($signer, $identity, true, $timeClaims);
            try {
                (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
                    self::certificateJson([$token]),
                    CertificateChainMode::SelfSignedExplicit,
                    self::NOW,
                );
                self::fail('Invalid time claim was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSignatureAlgorithmCurveAndMutationAreRejected(): void
    {
        $signer = $this->generate();
        $identity = $this->generate();
        $valid = $this->certificate($signer, $identity, true);
        $parts = explode('.', $valid);
        $signature = Base64Url::decode($parts[2], 96);
        $signature[0] = chr(ord($signature[0]) ^ 1);
        $parts[2] = Base64Url::encode($signature);
        try {
            (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
                self::certificateJson([implode('.', $parts)]),
                CertificateChainMode::SelfSignedExplicit,
                self::NOW,
            );
            self::fail('Mutated signature was accepted.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }

        $wrongAlgorithm = explode('.', $valid);
        $wrongAlgorithm[0] = Base64Url::encode('{"alg":"none"}');
        try {
            (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
                self::certificateJson([implode('.', $wrongAlgorithm)]),
                CertificateChainMode::SelfSignedExplicit,
                self::NOW,
            );
            self::fail('Wrong algorithm was accepted.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }

        $p256 = openssl_pkey_new([
            'config' => self::configurationFile(),
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $p256);
        $wrongCurve = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => self::exportAnyPublicKey($p256)],
            self::identityPayload($identity),
            $signer->privateKey,
        );
        $this->expectException(MalformedDataException::class);
        (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
            self::certificateJson([$wrongCurve]),
            CertificateChainMode::SelfSignedExplicit,
            self::NOW,
        );
    }

    public function testCertificateJsonBoundsDuplicatesDepthAndClaims(): void
    {
        $signer = $this->generate();
        $identity = $this->generate();
        $token = $this->certificate($signer, $identity, true);
        $verifier = new LegacyCertificateChainVerifier($signer->publicKey);
        foreach ([
            '{"chain":[],"chain":[]}',
            '{"chain":[[[]]]}',
            '{"chain":[]}',
            '{"chain":[1]}',
        ] as $json) {
            try {
                $verifier->verify($json, CertificateChainMode::SelfSignedExplicit, self::NOW);
                self::fail('Malformed certificate JSON was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
        $byteTight = new LegacyCertificateChainVerifier(
            $signer->publicKey,
            limits: new IdentityProofLimits(maximumCertificateJsonBytes: 8),
        );
        try {
            $byteTight->verify(self::certificateJson([$token]), CertificateChainMode::SelfSignedExplicit, self::NOW);
            self::fail('Certificate byte bound was not enforced.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }
        $tokenTight = new LegacyCertificateChainVerifier(
            $signer->publicKey,
            limits: new IdentityProofLimits(maximumCertificateJsonTokens: 3),
        );
        try {
            $tokenTight->verify(self::certificateJson([$token]), CertificateChainMode::SelfSignedExplicit, self::NOW);
            self::fail('Certificate token bound was not enforced.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }
        $depthTight = new LegacyCertificateChainVerifier(
            $signer->publicKey,
            limits: new IdentityProofLimits(jws: new SecurityLimits(maximumJsonDepth: 2)),
        );
        $deep = json_encode(['chain' => [$token], 'nested' => ['more' => []]], JSON_THROW_ON_ERROR);
        self::assertIsString($deep);
        $this->expectException(MalformedDataException::class);
        $depthTight->verify($deep, CertificateChainMode::SelfSignedExplicit, self::NOW);
    }

    public function testMissingFinalIdentityClaimsAreRejected(): void
    {
        $signer = $this->generate();
        $identity = $this->generate();
        $token = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($signer->publicKey)],
            ['identityPublicKey' => P384::exportPublicDerBase64($identity->publicKey)],
            $signer->privateKey,
        );
        $this->expectException(MalformedDataException::class);
        (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
            self::certificateJson([$token]),
            CertificateChainMode::SelfSignedExplicit,
            self::NOW,
        );
    }

    public function testExplicitNullTimeClaimIsRejected(): void
    {
        $signer = $this->generate();
        $identity = $this->generate();
        foreach (['nbf', 'exp'] as $name) {
            $payload = self::identityPayload($identity);
            $payload[$name] = null;
            $token = CompactJws::sign(
                ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($signer->publicKey)],
                $payload,
                $signer->privateKey,
            );
            try {
                (new LegacyCertificateChainVerifier($signer->publicKey))->verify(
                    self::certificateJson([$token]),
                    CertificateChainMode::SelfSignedExplicit,
                    self::NOW,
                );
                self::fail("Null {$name} claim was accepted");
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testOnlineRootIsFixedAtSecondCertificate(): void
    {
        $root = $this->generate();
        $second = $this->generate();
        $third = $this->generate();
        $identity = $this->generate();
        $json = self::certificateJson([
            $this->certificate($root, $second),
            $this->certificate($second, $third),
            $this->certificate($third, $identity, true),
        ]);
        $this->expectException(MalformedDataException::class);
        (new LegacyCertificateChainVerifier($root->publicKey))->verify($json, CertificateChainMode::OnlineLegacy, self::NOW);
    }

    public function testClientDataWrongKeyMutationAndMalformedClaimsAreRejected(): void
    {
        $identity = $this->generate();
        $other = $this->generate();
        $valid = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
            self::validClientClaims(),
            $identity->privateKey,
        );
        try {
            (new ClientDataJwtVerifier())->verify($valid, $other->publicKey);
            self::fail('Client data verified with the wrong identity key.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }

        $parts = explode('.', $valid);
        $signature = Base64Url::decode($parts[2], 96);
        $signature[95] = chr(ord($signature[95]) ^ 1);
        $parts[2] = Base64Url::encode($signature);
        try {
            (new ClientDataJwtVerifier())->verify(implode('.', $parts), $identity->publicKey);
            self::fail('Mutated client-data signature was accepted.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }

        foreach ([
            array_replace(self::validClientClaims(), ['SkinData' => base64_encode('bad')]),
            array_replace(self::validClientClaims(), ['SkinImageWidth' => 0]),
            array_replace(self::validClientClaims(), ['CapeImageWidth' => 1]),
            array_replace(self::validClientClaims(), ['SkinGeometryData' => base64_encode('not-json')]),
            array_replace(self::validClientClaims(), ['AnimatedImageData' => array_fill(0, 33, [])]),
        ] as $claims) {
            $jwt = CompactJws::sign(
                ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
                $claims,
                $identity->privateKey,
            );
            try {
                (new ClientDataJwtVerifier())->verify($jwt, $identity->publicKey);
                self::fail('Malformed client claims were accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testClientDataDoesNotRequireUnspecifiedX5uHeader(): void
    {
        $identity = $this->generate();
        $jwt = CompactJws::sign(
            ['alg' => 'ES384'],
            self::validClientClaims(),
            $identity->privateKey,
        );
        $verified = (new ClientDataJwtVerifier())->verify($jwt, $identity->publicKey);
        self::assertSame("\x01\x02\x03\x04", $verified->skin);
    }

    public function testClientDataAcceptsRetailNullGeometryAndRejectsOtherScalarShapes(): void
    {
        $identity = $this->generate();
        $claims = self::validClientClaims();
        $claims['SkinGeometryData'] = base64_encode("null\n");
        $jwt = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
            $claims,
            $identity->privateKey,
        );
        self::assertSame("null\n", (new ClientDataJwtVerifier())->verify($jwt, $identity->publicKey)->geometryJson);

        foreach (['[]', 'true', '0', '"null"'] as $geometry) {
            $claims['SkinGeometryData'] = base64_encode($geometry);
            $jwt = CompactJws::sign(
                ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
                $claims,
                $identity->privateKey,
            );
            try {
                (new ClientDataJwtVerifier())->verify($jwt, $identity->publicKey);
                self::fail('Unsupported client geometry JSON shape was accepted.');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testClientAggregateLimitIsEnforced(): void
    {
        $identity = $this->generate();
        $jwt = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
            self::validClientClaims(),
            $identity->privateKey,
        );
        $this->expectException(MalformedDataException::class);
        (new ClientDataJwtVerifier(new ClientDataLimits(
            maximumSkinBytes: 4,
            maximumCapeBytes: 1,
            maximumGeometryBytes: 2,
            maximumAggregateDecodedBytes: 9,
        )))
            ->verify($jwt, $identity->publicKey);
    }

    public function testDefaultClientLimitsAcceptBounded128PixelSkin(): void
    {
        $identity = $this->generate();
        $claims = self::validClientClaims();
        $claims['SkinImageWidth'] = 128;
        $claims['SkinImageHeight'] = 128;
        $claims['SkinData'] = base64_encode(str_repeat("\x7f", 128 * 128 * 4));
        $jwt = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
            $claims,
            $identity->privateKey,
            new ClientDataLimits()->jws,
        );
        $verified = (new ClientDataJwtVerifier())->verify($jwt, $identity->publicKey);
        self::assertSame(65_536, strlen($verified->skin));
    }

    public function testGeometryLexicalTokenLimitIsEnforced(): void
    {
        $identity = $this->generate();
        $claims = self::validClientClaims();
        $claims['SkinGeometryData'] = base64_encode('{"a":[1,2,3]}');
        $jwt = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identity->publicKey)],
            $claims,
            $identity->privateKey,
        );
        $this->expectException(MalformedDataException::class);
        (new ClientDataJwtVerifier(new ClientDataLimits(maximumGeometryJsonTokens: 5)))
            ->verify($jwt, $identity->publicKey);
    }

    public function testClientLimitsRejectAggregateThatCannotFitJwsPayload(): void
    {
        $this->expectException(InvalidValueException::class);
        new ClientDataLimits(jws: new SecurityLimits(maximumPayloadBytes: 1_000));
    }

    public function testClientCompactLimitAndImmutableResults(): void
    {
        [$json, $root, $identityKey] = $this->onlineChain();
        $identity = (new LegacyCertificateChainVerifier($root->publicKey))->verify(
            $json,
            CertificateChainMode::OnlineLegacy,
            self::NOW,
        );
        $jwt = CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($identityKey->publicKey)],
            self::validClientClaims(),
            $identityKey->privateKey,
        );
        try {
            (new ClientDataJwtVerifier(new ClientDataLimits(jws: new SecurityLimits(
                maximumCompactJwsBytes: 32,
                maximumPayloadBytes: 780_000,
            ))))
                ->verify($jwt, $identity->identityPublicKey);
            self::fail('Client compact-JWS bound was not enforced.');
        } catch (MalformedDataException) {
            self::addToAssertionCount(1);
        }
        $client = (new ClientDataJwtVerifier())->verify($jwt, $identity->identityPublicKey);
        $identityProperty = new \ReflectionProperty($identity, 'displayName');
        self::assertTrue($identityProperty->isReadOnly());
        $clientProperty = new \ReflectionProperty($client, 'skinWidth');
        self::assertTrue($clientProperty->isReadOnly());
        $this->expectException(\Error::class);
        $clientProperty->setValue($client, 2);
    }

    /** @return array{string, P384KeyPair, P384KeyPair} */
    private function onlineChain(): array
    {
        $first = $this->generate();
        $root = $this->generate();
        $third = $this->generate();
        $identity = $this->generate();
        return [self::certificateJson([
            $this->certificate($first, $root),
            $this->certificate($root, $third),
            $this->certificate($third, $identity, true),
        ]), $root, $identity];
    }

    /** @param array<string, int> $additional */
    private function certificate(P384KeyPair $signer, P384KeyPair $next, bool $final = false, array $additional = []): string
    {
        $payload = self::identityPayload($next, $final) + $additional + ['nbf' => self::NOW - 10, 'exp' => self::NOW + 10];
        return CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($signer->publicKey)],
            $payload,
            $signer->privateKey,
        );
    }

    /** @return array<string, mixed> */
    private static function identityPayload(P384KeyPair $next, bool $final = true): array
    {
        $payload = ['identityPublicKey' => P384::exportPublicDerBase64($next->publicKey)];
        if ($final) {
            $payload['extraData'] = [
                'displayName' => 'Veno Player',
                'identity' => '123e4567-e89b-42d3-a456-426614174000',
                'XUID' => '123456789',
            ];
        }
        return $payload;
    }

    /** @return array<string, mixed> */
    private static function validClientClaims(): array
    {
        return [
            'SkinImageWidth' => 1,
            'SkinImageHeight' => 1,
            'SkinData' => base64_encode("\x01\x02\x03\x04"),
            'CapeImageWidth' => 0,
            'CapeImageHeight' => 0,
            'CapeData' => '',
            'SkinGeometryData' => base64_encode('{}'),
            'SkinId' => 'skin-id',
            'PlayFabId' => 'playfab-id',
            'SkinResourcePatch' => base64_encode('{"geometry":{"default":"geometry.humanoid.custom"}}'),
            'SkinGeometryDataEngineVersion' => '1.0.0',
            'SkinAnimationData' => base64_encode('{}'),
            'CapeId' => '',
            'FullSkinId' => 'full-skin-id',
            'ArmSize' => 'wide',
            'SkinColor' => '#0',
            'PremiumSkin' => false,
            'PersonaSkin' => false,
            'CapeOnClassicSkin' => false,
            'IsPrimaryUser' => true,
            'OverrideSkin' => false,
            'ProfileHash' => 'profile-hash',
            'DeviceOS' => DeviceOs::Windows->value,
            'AnimatedImageData' => [[
                'ImageWidth' => 1,
                'ImageHeight' => 1,
                'Image' => base64_encode("\x05\x06\x07\x08"),
                'Type' => 0,
                'Frames' => 1.0,
                'ExpressionType' => 0,
            ]],
        ];
    }

    /** @param list<string> $chain */
    private static function certificateJson(array $chain): string
    {
        $json = json_encode(['chain' => $chain], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertIsString($json);
        return $json;
    }

    private function generate(): P384KeyPair
    {
        return (new OpenSslEphemeralKeyFactory(self::configurationFile()))->generate();
    }

    private static function exportAnyPublicKey(OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $pem = $details['key'] ?? null;
        self::assertIsString($pem);
        $base64 = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $pem);
        self::assertIsString($base64);
        return $base64;
    }

    private static function configurationFile(): string
    {
        return dirname(__DIR__) . '/Fixtures/openssl.cnf';
    }
}
