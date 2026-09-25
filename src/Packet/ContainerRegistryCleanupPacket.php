<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded clientbound release of full-container registry entries. */
final readonly class ContainerRegistryCleanupPacket implements Packet
{
    public const int MAXIMUM_CONTAINERS = 128;

    /** @param list<FullContainerName> $containers */
    public function __construct(public array $containers)
    {
        CodecSupport::validateCount($containers, self::MAXIMUM_CONTAINERS, 'Container registry cleanup');
        foreach ($containers as $container) {
            if (!$container instanceof FullContainerName) {
                throw new InvalidValueException('Container registry cleanup must contain full container names.');
            }
        }
    }

    public function packetId(): int { return PacketIds::CONTAINER_REGISTRY_CLEANUP; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->containers));
        foreach ($this->containers as $container) {
            $writer = FullContainerNameWireCodec::write($writer, $container);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_CONTAINERS) {
            throw new MalformedDataException('Container registry cleanup count exceeds its limit.');
        }
        $containers = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            [$containers[], $reader] = FullContainerNameWireCodec::read($reader);
        }
        CodecSupport::requireEnd($reader);
        return new self($containers);
    }
}
