<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class PlayStatusPacket implements Packet
{
    public function __construct(public PlayStatus $status)
    {
    }

    public function packetId(): int
    {
        return PacketIds::PLAY_STATUS;
    }

    public function encode(): string
    {
        return CodecSupport::writeSignedIntBE(CodecSupport::writer(), $this->status->value)->toString();
    }

    public static function decode(string $bytes): self
    {
        [$value, $reader] = CodecSupport::readSignedIntBE(CodecSupport::reader($bytes));
        CodecSupport::requireEnd($reader);
        $status = PlayStatus::tryFrom($value);
        if ($status === null) {
            throw new MalformedDataException('Unknown play status.');
        }
        return new self($status);
    }
}
