<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class NetworkSettingsPacket implements Packet
{
    public function __construct(
        public int $compressionThreshold,
        public CompressionAlgorithm $compressionAlgorithm,
        public bool $clientThrottleEnabled,
        public int $clientThrottleThreshold,
        public float $clientThrottleScalar,
    ) {
        if ($compressionThreshold < 0 || $compressionThreshold > 0xffff) {
            throw new InvalidValueException('Compression threshold must fit in an unsigned short.');
        }
        if ($clientThrottleThreshold < 0 || $clientThrottleThreshold > 0xff) {
            throw new InvalidValueException('Client throttle threshold must fit in an unsigned byte.');
        }
        if (!is_finite($clientThrottleScalar)) {
            throw new InvalidValueException('Client throttle scalar must be finite.');
        }
    }

    public function packetId(): int
    {
        return PacketIds::NETWORK_SETTINGS;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedShortLE($this->compressionThreshold)
            ->writeUnsignedShortLE($this->compressionAlgorithm->value);
        $writer = CodecSupport::writeBoolean($writer, $this->clientThrottleEnabled)
            ->writeUnsignedByte($this->clientThrottleThreshold)
            ->writeFloatLE($this->clientThrottleScalar);
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $threshold = CodecSupport::reader($bytes)->readUnsignedShortLE();
        $algorithm = $threshold->reader->readUnsignedShortLE();
        $compression = CompressionAlgorithm::tryFrom($algorithm->value);
        if ($compression === null) {
            throw new MalformedDataException('Unknown compression algorithm.');
        }
        [$throttleEnabled, $reader] = CodecSupport::readBoolean($algorithm->reader);
        $throttleThreshold = $reader->readUnsignedByte();
        $scalar = $throttleThreshold->reader->readFloatLE();
        CodecSupport::requireEnd($scalar->reader);
        if (!is_finite($scalar->value)) {
            throw new MalformedDataException('Client throttle scalar must be finite.');
        }
        return new self($threshold->value, $compression, $throttleEnabled, $throttleThreshold->value, $scalar->value);
    }
}
