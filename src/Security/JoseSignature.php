<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use Bedriox\Protocol\Exception\MalformedDataException;

final class JoseSignature
{
    public const RAW_BYTES = 96;
    private const string P384_ORDER_HEX = 'ffffffffffffffffffffffffffffffffffffffffffffffffc7634d81f4372ddf581a0db248b0a77aecec196accc52973';

    public static function rawToDer(string $raw) : string
    {
        if (strlen($raw) !== self::RAW_BYTES) {
            throw new MalformedDataException('ES384 signature must contain exactly 96 bytes');
        }
        $rawR = substr($raw, 0, 48);
        $rawS = substr($raw, 48);
        self::assertScalar($rawR);
        self::assertScalar($rawS);
        $r = self::encodeInteger($rawR);
        $s = self::encodeInteger($rawS);
        $body = "\x02" . self::encodeByte(strlen($r)) . $r . "\x02" . self::encodeByte(strlen($s)) . $s;

        return "\x30" . self::encodeByte(strlen($body)) . $body;
    }

    private static function encodeByte(int $value) : string
    {
        if ($value < 0 || $value > 0xff) {
            throw new MalformedDataException('DER byte value is outside the unsigned-byte range');
        }

        return pack('C', $value);
    }

    public static function derToRaw(string $der) : string
    {
        $offset = 0;
        if (strlen($der) < 8 || ord($der[$offset++]) !== 0x30) {
            throw new MalformedDataException('Invalid ECDSA DER sequence');
        }
        $length = self::readLength($der, $offset);
        if ($length !== strlen($der) - $offset) {
            throw new MalformedDataException('ECDSA DER sequence length mismatch');
        }
        $r = self::readInteger($der, $offset);
        $s = self::readInteger($der, $offset);
        if ($offset !== strlen($der)) {
            throw new MalformedDataException('Trailing ECDSA DER bytes');
        }

        $rawR = str_pad($r, 48, "\0", STR_PAD_LEFT);
        $rawS = str_pad($s, 48, "\0", STR_PAD_LEFT);
        self::assertScalar($rawR);
        self::assertScalar($rawS);

        return $rawR . $rawS;
    }

    private static function encodeInteger(string $integer) : string
    {
        $integer = ltrim($integer, "\0");
        if ($integer === '') {
            $integer = "\0";
        }
        if ((ord($integer[0]) & 0x80) !== 0) {
            $integer = "\0" . $integer;
        }

        return $integer;
    }

    private static function readLength(string $der, int &$offset) : int
    {
        if (!isset($der[$offset])) {
            throw new MalformedDataException('Truncated ECDSA DER length');
        }
        $length = ord($der[$offset++]);
        if (($length & 0x80) !== 0) {
            throw new MalformedDataException('Non-canonical ECDSA DER length');
        }

        return $length;
    }

    private static function readInteger(string $der, int &$offset) : string
    {
        if (!isset($der[$offset]) || ord($der[$offset++]) !== 0x02) {
            throw new MalformedDataException('Missing ECDSA DER integer');
        }
        $length = self::readLength($der, $offset);
        if ($length < 1 || $length > 49 || $offset + $length > strlen($der)) {
            throw new MalformedDataException('Invalid ECDSA DER integer length');
        }
        $integer = substr($der, $offset, $length);
        $offset += $length;
        if ((ord($integer[0]) & 0x80) !== 0 || ($length > 1 && $integer[0] === "\0" && (ord($integer[1]) & 0x80) === 0)) {
            throw new MalformedDataException('Non-canonical ECDSA DER integer');
        }
        if ($integer[0] === "\0") {
            $integer = substr($integer, 1);
        }
        if (strlen($integer) > 48 || $integer === '' || trim($integer, "\0") === '') {
            throw new MalformedDataException('ECDSA integer is outside the ES384 domain');
        }

        return $integer;
    }

    private static function assertScalar(string $scalar) : void
    {
        $order = pack('H*', self::P384_ORDER_HEX);
        if (strlen($scalar) !== 48 || trim($scalar, "\0") === '' || strcmp($scalar, $order) >= 0) {
            throw new MalformedDataException('ECDSA scalar is outside the P-384 signature domain');
        }
    }
}
