<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

interface Packet
{
    public function packetId(): int;

    public function encode(): string;
}
