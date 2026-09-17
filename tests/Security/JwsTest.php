<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Security;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Security\Base64Url;
use Bedriox\Protocol\Security\CompactJws;
use Bedriox\Protocol\Security\HandshakeJwt;
use Bedriox\Protocol\Security\OpenSslEphemeralKeyFactory;
use Bedriox\Protocol\Security\P384;
use Bedriox\Protocol\Security\P384KeyPair;
use Bedriox\Protocol\Security\SecurityLimits;

final class JwsTest extends TestCase
{
    public function testHandshakeRoundTripAndVerification() : void
    {
        $pair = $this->generate();
        $salt = "0123456789abcdef";
        $token = HandshakeJwt::create($pair, $salt);
        $parsed = HandshakeJwt::parse($token);
        $public = P384::importPublicDerBase64(self::stringMember($parsed->header, 'x5u'));

        self::assertSame('ES384', $parsed->header['alg']);
        self::assertSame(base64_encode($salt), $parsed->payload['salt']);
        self::assertTrue(CompactJws::verify($parsed, $public));
    }

    public function testWrongAlgorithmIsRejectedBeforeVerification() : void
    {
        $pair = $this->generate();
        $token = HandshakeJwt::create($pair, '0123456789abcdef');
        $parts = explode('.', $token);
        $parts[0] = Base64Url::encode('{"alg":"none","x5u":"unused"}');

        $this->expectException(MalformedDataException::class);
        CompactJws::parse(implode('.', $parts));
    }

    public function testUnsupportedHeadersAreRejectedByPresenceEvenWhenNull() : void
    {
        $pair = $this->generate();
        foreach (['crit', 'b64'] as $name) {
            $header = Base64Url::encode(json_encode(['alg' => 'ES384', $name => null], JSON_THROW_ON_ERROR));
            $payload = Base64Url::encode('{"value":1}');
            $input = $header . '.' . $payload;
            $token = $input . '.' . Base64Url::encode(P384::sign($input, $pair->privateKey));
            try {
                CompactJws::parse($token);
                self::fail("Unsupported {$name} header was accepted");
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(MalformedDataException::class);
        CompactJws::sign(['alg' => 'ES384', 'crit' => null], ['value' => 1], $pair->privateKey);
    }

    public function testSignatureMutationDoesNotVerify() : void
    {
        $pair = $this->generate();
        $parsed = CompactJws::parse(CompactJws::sign(['alg' => 'ES384'], ['value' => 1], $pair->privateKey));
        $mutated = $parsed->signature;
        $mutated[95] = chr(ord($mutated[95]) ^ 1);
        self::assertFalse(P384::verify($parsed->signingInput, $mutated, $pair->publicKey));
    }

    public function testJwsInputLimitAndMalformedSegments() : void
    {
        $this->expectException(MalformedDataException::class);
        CompactJws::parse(str_repeat('x', 40), new SecurityLimits(maximumCompactJwsBytes: 32));
    }

    /** @param array<string, mixed> $object */
    private static function stringMember(array $object, string $name) : string
    {
        $value = $object[$name] ?? null;
        self::assertIsString($value);

        return $value;
    }

    private function generate() : P384KeyPair
    {
        return (new OpenSslEphemeralKeyFactory(dirname(__DIR__) . '/Fixtures/openssl.cnf'))->generate();
    }
}
