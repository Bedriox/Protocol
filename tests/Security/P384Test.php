<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Security;

use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Security\OpenSslEphemeralKeyFactory;
use Bedriox\Protocol\Security\P384;
use Bedriox\Protocol\Security\P384KeyPair;
use Bedriox\Protocol\Security\SecurityLimits;

final class P384Test extends TestCase
{
    public function testGenerateExportImportSignVerifyAndMutations() : void
    {
        $pair = $this->generate();
        $encoded = P384::exportPublicDerBase64($pair->publicKey);
        $imported = P384::importPublicDerBase64($encoded);
        $signature = P384::sign('bounded message', $pair->privateKey);

        self::assertSame(96, strlen($signature));
        self::assertTrue(P384::verify('bounded message', $signature, $imported));
        self::assertFalse(P384::verify('mutated message', $signature, $imported));
        $signature[0] = chr(ord($signature[0]) ^ 1);
        self::assertFalse(P384::verify('bounded message', $signature, $imported));
    }

    public function testEcdhIsSymmetricAndKeyDerivationHasKnownAnswer() : void
    {
        $alice = $this->generate();
        $bob = $this->generate();
        $one = P384::deriveSharedSecret($alice->privateKey, $bob->publicKey);
        $two = P384::deriveSharedSecret($bob->privateKey, $alice->publicKey);
        self::assertSame($one, $two);

        $knownSecret = implode('', array_map(chr(...), range(0, 47)));
        self::assertSame(
            '4cccdf8a61be560f2da6921ffd227b20c4282a111ad38f1639935aea7ab174d1',
            bin2hex(P384::deriveSessionKey(str_repeat("\x01", 16), $knownSecret)),
        );
    }

    public function testRejectsWrongCurve() : void
    {
        $key = openssl_pkey_new([
            'config' => self::configurationFile(),
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        self::assertNotFalse($key, 'The repository test OpenSSL configuration must support P-256');
        $this->expectException(InvalidValueException::class);
        P384::assertPrivateKey($key);
    }

    public function testRejectsWrongCurveSpki() : void
    {
        $key = openssl_pkey_new([
            'config' => self::configurationFile(),
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        self::assertNotFalse($key, 'The repository test OpenSSL configuration must support P-256');
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($details['key']);
        $base64 = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $details['key']);
        self::assertIsString($base64);

        $this->expectException(InvalidValueException::class);
        P384::importPublicDerBase64($base64);
    }

    public function testRejectsMismatchedPublicAndPrivateKeyPair() : void
    {
        $private = $this->generate();
        $other = $this->generate();
        $this->expectException(InvalidValueException::class);
        new P384KeyPair($private->privateKey, $other->publicKey);
    }

    public function testRejectsMalformedAndOversizedSpki() : void
    {
        $this->expectException(MalformedDataException::class);
        P384::importPublicDerBase64(base64_encode(str_repeat('x', 33)), new SecurityLimits(maximumSpkiDerBytes: 32));
    }

    private function generate() : P384KeyPair
    {
        return (new OpenSslEphemeralKeyFactory(self::configurationFile()))->generate();
    }

    private static function configurationFile() : string
    {
        return dirname(__DIR__) . '/Fixtures/openssl.cnf';
    }
}
