<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Identity;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Identity\ClientDataJwt;
use Bedriox\Protocol\Identity\ClientDataJwtVerifier;
use Bedriox\Protocol\Identity\GatewayForwardingClaims;
use Bedriox\Protocol\Identity\GatewayForwardingToken;
use Bedriox\Protocol\Identity\VerifiedAnimation;
use Bedriox\Protocol\Identity\VerifiedClientData;
use Bedriox\Protocol\Security\OpenSslEphemeralKeyFactory;
use Bedriox\Protocol\Security\P384;
use Bedriox\Protocol\Value\DeviceOs;
use PHPUnit\Framework\TestCase;

final class GatewayForwardingTokenTest extends TestCase
{
    public function testSignedClaimsRoundTrip(): void
    {
        $keys = $this->keys();
        $claims = new GatewayForwardingClaims(
            'gateway-one',
            'lobby',
            'account-subject',
            '123e4567-e89b-42d3-a456-426614174000',
            'Veno Player',
            '123456789',
            '203.0.113.7',
            49_152,
            2_193,
            P384::exportPublicDerBase64($keys->publicKey),
            1_000,
            999,
            1_015,
            'c29tZS11bmlxdWUtdG9rZW4',
            'minecraft-player-id',
            424_242,
            true,
        );
        $secret = str_repeat("\x5a", 32);

        $decoded = GatewayForwardingToken::verify(GatewayForwardingToken::issue($claims, $secret), $secret);

        self::assertEquals($claims, $decoded);
        self::assertSame(424_242, $decoded->runtimeEntityId);
        self::assertTrue($decoded->operator);
        self::assertSame(
            P384::exportPublicDerBase64($keys->publicKey),
            P384::exportPublicDerBase64($decoded->publicKey()),
        );
    }

    public function testTamperingAndShortSecretsFailClosed(): void
    {
        $keys = $this->keys();
        $claims = new GatewayForwardingClaims(
            'gateway-one', 'lobby', 'subject', '123e4567-e89b-42d3-a456-426614174000', 'Player', '1',
            '127.0.0.1', 50_000, 2_193, P384::exportPublicDerBase64($keys->publicKey), 1, 1, 2, 'token',
        );
        $token = GatewayForwardingToken::issue($claims, str_repeat('s', 32));

        try {
            GatewayForwardingToken::verify(substr($token, 0, -1) . ($token[-1] === 'A' ? 'B' : 'A'), str_repeat('s', 32));
            self::fail('A modified forwarding token was accepted.');
        } catch (MalformedDataException) {
        }

        $this->expectException(InvalidValueException::class);
        GatewayForwardingToken::issue($claims, 'short');
    }

    public function testSeparatorFloodIsRejected(): void
    {
        $this->expectException(MalformedDataException::class);
        GatewayForwardingToken::verify(str_repeat('x.', 4_000), str_repeat('s', 32));
    }

    public function testVerifiedClientDataCanBeResignedForDownstream(): void
    {
        $keys = $this->keys();
        $data = new VerifiedClientData(
            1,
            1,
            "\x01\x02\x03\x04",
            0,
            0,
            '',
            '{}',
            [new VerifiedAnimation(1, 1, "\x05\x06\x07\x08", 0, 1.0)],
            'skin-id',
            'playfab-id',
            '{"geometry":{"default":"geometry.humanoid.custom"}}',
            '1.0.0',
            '{}',
            '',
            'full-skin-id',
            'wide',
            '#0',
            false,
            false,
            false,
            true,
            false,
            'profile-hash',
            DeviceOs::Windows,
        );

        $verified = (new ClientDataJwtVerifier())->verify(ClientDataJwt::create($data, $keys), $keys->publicKey);

        self::assertEquals($data, $verified);
    }

    private function keys(): \Bedriox\Protocol\Security\P384KeyPair
    {
        return (new OpenSslEphemeralKeyFactory(__DIR__ . '/../Fixtures/openssl.cnf'))->generate();
    }
}
