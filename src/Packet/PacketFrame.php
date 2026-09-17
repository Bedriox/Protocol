<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Immutable packet header and still-uninterpreted packet-specific bytes. */
final readonly class PacketFrame
{
    public function __construct(
        public PacketHeader $header,
        public string $payload,
    ) {}
}
