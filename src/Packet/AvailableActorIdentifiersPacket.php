<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class AvailableActorIdentifiersPacket implements Packet
{
    public function __construct(public string $networkNbt)
    {
        if ($networkNbt === '' || strlen($networkNbt) > 1_048_576 || ord($networkNbt[0]) !== 10) {
            throw new InvalidValueException('Actor-identifier network NBT is empty, oversized, or not a root compound.');
        }
    }

    /**
     * Builds the network-NBT actor registry from the server's admitted entity types.
     *
     * @param list<string> $identifiers
     */
    public static function fromIdentifiers(array $identifiers): self
    {
        if ($identifiers === [] || !array_is_list($identifiers) || count($identifiers) > 1_024
            || count(array_unique($identifiers)) !== count($identifiers)) {
            throw new InvalidValueException('Actor identifiers must be a non-empty unique bounded list.');
        }

        $writer = CodecSupport::writer()->writeBytes("\x0a\x00\x09")->writeString('idlist', 64)
            ->writeUnsignedByte(10)->writeUnsignedVarInt(count($identifiers));
        foreach ($identifiers as $identifier) {
            if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1) {
                throw new InvalidValueException('Actor identifier is not a namespaced identifier.');
            }
            $writer = $writer->writeUnsignedByte(8)->writeString('id', 64)->writeString($identifier, 256)
                ->writeUnsignedByte(0);
        }

        return new self($writer->writeUnsignedByte(0)->toString());
    }

    public function packetId(): int { return PacketIds::AVAILABLE_ACTOR_IDENTIFIERS; }
    public function encode(): string { return $this->networkNbt; }
}
