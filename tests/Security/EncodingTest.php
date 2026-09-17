<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Security\Base64Url;
use Bedriox\Protocol\Security\BoundedJson;
use Bedriox\Protocol\Security\JoseSignature;

final class EncodingTest extends TestCase
{
    public function testBase64UrlKnownAnswer() : void
    {
        self::assertSame('AAH-_w', Base64Url::encode("\x00\x01\xfe\xff"));
        self::assertSame("\x00\x01\xfe\xff", Base64Url::decode('AAH-_w', 4));
    }

    #[DataProvider('invalidBase64UrlProvider')]
    public function testRejectsNonCanonicalBase64Url(string $encoded) : void
    {
        $this->expectException(MalformedDataException::class);
        Base64Url::decode($encoded, 32);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBase64UrlProvider() : iterable
    {
        yield 'padding' => ['YQ=='];
        yield 'standard alphabet' => ['+w'];
        yield 'whitespace' => ["YQ\n"];
        yield 'impossible length' => ['a'];
    }

    public function testBoundedJsonRejectsDuplicateAndDepth() : void
    {
        $this->expectException(MalformedDataException::class);
        BoundedJson::decodeObject('{"alg":"ES384","alg":"none"}', 128, 8);
    }

    public function testBoundedJsonRejectsExcessDepth() : void
    {
        $this->expectException(MalformedDataException::class);
        BoundedJson::decodeObject('{"a":{"b":{"c":1}}}', 128, 3);
    }

    public function testRawDerKnownAnswerAndCanonicalRoundTrip() : void
    {
        $raw = str_repeat("\0", 47) . "\x80" . str_repeat("\0", 47) . "\x01";
        $der = "\x30\x07\x02\x02\x00\x80\x02\x01\x01";
        self::assertSame($der, JoseSignature::rawToDer($raw));
        self::assertSame($raw, JoseSignature::derToRaw($der));
    }

    public function testEveryKnownAnswerDerTruncationIsRejected() : void
    {
        $der = "\x30\x06\x02\x01\x01\x02\x01\x02";
        for ($length = 0; $length < strlen($der); ++$length) {
            try {
                JoseSignature::derToRaw(substr($der, 0, $length));
                self::fail('DER truncation at byte ' . $length . ' was accepted');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsZeroAndCurveOrderScalarsSymmetrically() : void
    {
        $one = str_repeat("\0", 47) . "\x01";
        $zero = str_repeat("\0", 48);
        $order = pack('H*', 'ffffffffffffffffffffffffffffffffffffffffffffffffc7634d81f4372ddf581a0db248b0a77aecec196accc52973');
        $maximum = substr($order, 0, -1) . "\x72";
        self::assertSame($maximum . $one, JoseSignature::derToRaw(JoseSignature::rawToDer($maximum . $one)));
        foreach ([$zero . $one, $one . $zero, $order . $one, $one . $order] as $raw) {
            try {
                JoseSignature::rawToDer($raw);
                self::fail('Out-of-domain raw ES384 scalar was accepted');
            } catch (MalformedDataException) {
                self::addToAssertionCount(1);
            }
        }

        $orderInteger = "\0" . $order;
        $der = "\x30\x66\x02\x31" . $orderInteger . "\x02\x31" . $orderInteger;
        $this->expectException(MalformedDataException::class);
        JoseSignature::derToRaw($der);
    }

    #[DataProvider('invalidDerProvider')]
    public function testRejectsMalformedDer(string $der) : void
    {
        $this->expectException(MalformedDataException::class);
        JoseSignature::derToRaw($der);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDerProvider() : iterable
    {
        yield 'truncated' => ["\x30\x06\x02\x01\x01"];
        yield 'trailing' => ["\x30\x06\x02\x01\x01\x02\x01\x01\0"];
        yield 'negative' => ["\x30\x06\x02\x01\x80\x02\x01\x01"];
        yield 'redundant zero' => ["\x30\x07\x02\x02\x00\x01\x02\x01\x01"];
        yield 'long length' => ["\x30\x81\x06\x02\x01\x01\x02\x01\x01"];
        yield 'zero scalar' => ["\x30\x06\x02\x01\x00\x02\x01\x01"];
    }
}
