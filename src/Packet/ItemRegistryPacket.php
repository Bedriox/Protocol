<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ItemRegistryPacket implements Packet
{
    private function __construct(private string $payload)
    {
        if ($payload === '' || strlen($payload) > CodecSupport::MAX_PACKET_BYTES) { throw new InvalidValueException('Item registry is empty or oversized.'); }
    }

    /** @param array<string, array{runtime_id: int, component_based: bool, version: int, component_nbt?: string}> $items */
    public static function fromRequiredItems(array $items): self
    {
        if (count($items) > 5_000) { throw new InvalidValueException('Required-item registry count is oversized.'); }
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($items));
        foreach ($items as $identifier => $item) {
            if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $identifier) !== 1 || $item['runtime_id'] < -32768 || $item['runtime_id'] > 32767
                || $item['version'] < 0 || $item['version'] > 2) { throw new InvalidValueException('Required-item entry is outside Bedrock bounds.'); }
            // The supported Bedrock protocol identifies every vanilla item's component root by the item name,
            // including items whose root compound has no children.
            $nbt = CodecSupport::writer()->writeUnsignedByte(10)->writeString($identifier, 256)
                ->writeUnsignedByte(0)->toString();
            if (isset($item['component_nbt'])) {
                $decoded = base64_decode($item['component_nbt'], true);
                if (!is_string($decoded) || $decoded === '' || strlen($decoded) > 262_144 || ord($decoded[0]) !== 10) {
                    throw new InvalidValueException('Required-item component NBT is malformed or oversized.');
                }
                $nbt = LittleEndianNbtToNetwork::convert($decoded);
            }
            $writer = $writer->writeString($identifier, 256)->writeSignedShortLE($item['runtime_id']);
            $writer = CodecSupport::writeBoolean($writer, $item['component_based'])->writeSignedVarInt($item['version'])->writeBytes($nbt);
        }
        return new self($writer->toString());
    }

    public function packetId(): int { return PacketIds::ITEM_REGISTRY; }
    public function encode(): string { return $this->payload; }
}
