<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\CodecException;
use Bedriox\Protocol\Exception\MalformedDataException;

final class PlayerListPacketCodec
{
    public static function decode(string $bytes): Packet
    {
        if ($bytes === '') {
            throw new MalformedDataException('Player-list action is truncated.');
        }
        try {
            return PlayerListAddPacket::decode($bytes);
        } catch (CodecException) {
            return PlayerListRemovePacket::decode($bytes);
        }
    }
}
