<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Security;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class HandshakeJwt
{
    public static function create(P384KeyPair $keys, string $salt, SecurityLimits $limits = new SecurityLimits()) : string
    {
        if (strlen($salt) !== 16) {
            throw new InvalidValueException('Handshake salt must contain exactly 16 bytes');
        }

        return CompactJws::sign(
            ['alg' => 'ES384', 'x5u' => P384::exportPublicDerBase64($keys->publicKey, $limits)],
            ['salt' => base64_encode($salt)],
            $keys->privateKey,
            $limits,
        );
    }

    public static function parse(string $compact, SecurityLimits $limits = new SecurityLimits()) : ParsedJws
    {
        $jws = CompactJws::parse($compact, $limits);
        if (!isset($jws->header['x5u']) || !is_string($jws->header['x5u']) || !isset($jws->payload['salt']) || !is_string($jws->payload['salt'])) {
            throw new MalformedDataException('Handshake JWS lacks x5u or salt');
        }
        P384::importPublicDerBase64($jws->header['x5u'], $limits);
        $salt = base64_decode($jws->payload['salt'], true);
        if ($salt === false || strlen($salt) !== 16 || base64_encode($salt) !== $jws->payload['salt']) {
            throw new MalformedDataException('Handshake salt must be canonical standard base64 for 16 bytes');
        }

        return $jws;
    }
}
