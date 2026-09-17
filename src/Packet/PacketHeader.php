<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Immutable Bedrock packet routing header for the Bedrock protocol. */
final readonly class PacketHeader
{
    public const int MAXIMUM_PACKET_ID = 0x3ff;
    public const int MAXIMUM_SUBCLIENT_ID = 3;

    public function __construct(
        public int $packetId,
        public int $senderSubclientId = 0,
        public int $targetSubclientId = 0,
    ) {
        if ($packetId < 0 || $packetId > self::MAXIMUM_PACKET_ID) {
            throw new InvalidValueException('Packet ID must fit in 10 bits.');
        }
        if ($senderSubclientId < 0 || $senderSubclientId > self::MAXIMUM_SUBCLIENT_ID) {
            throw new InvalidValueException('Sender subclient ID must fit in 2 bits.');
        }
        if ($targetSubclientId < 0 || $targetSubclientId > self::MAXIMUM_SUBCLIENT_ID) {
            throw new InvalidValueException('Target subclient ID must fit in 2 bits.');
        }
    }

    public function packed(): int
    {
        return $this->packetId | ($this->senderSubclientId << 10) | ($this->targetSubclientId << 12);
    }
}
