<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class Base64Url
{
    public static function encode(string $bytes) : string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $encoded, int $maximumDecodedBytes) : string
    {
        if ($maximumDecodedBytes < 0) {
            throw new InvalidValueException('Maximum decoded size cannot be negative');
        }
        if (strlen($encoded) > (($maximumDecodedBytes + 2) * 4) || preg_match('/[^A-Za-z0-9_-]/D', $encoded) === 1) {
            throw new MalformedDataException('Invalid base64url value');
        }
        $remainder = strlen($encoded) % 4;
        if ($remainder === 1) {
            throw new MalformedDataException('Invalid base64url length');
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - $remainder) % 4), true);
        if ($decoded === false || strlen($decoded) > $maximumDecodedBytes || self::encode($decoded) !== $encoded) {
            throw new MalformedDataException('Invalid or oversized base64url value');
        }

        return $decoded;
    }
}
