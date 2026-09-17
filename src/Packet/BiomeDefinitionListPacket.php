<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Bedrock clientbound biome definitions built from Bedriox/Data's validated array shape. */
final readonly class BiomeDefinitionListPacket implements Packet
{
    private function __construct(private string $payload) {}

    /**
     * @param list<array{name: string, id: int, temperature: float, downfall: float, foliage_snow: float,
     *     depth: float, scale: float, map_water_argb: int, rain: bool, tags: list<string>}> $definitions
     */
    public static function fromDefinitions(array $definitions): self
    {
        if ($definitions === [] || count($definitions) > 1_024 || !array_is_list($definitions)) {
            throw new InvalidValueException('Biome definitions must be a non-empty bounded list.');
        }
        /** @var array<string, int> $stringIndexes */
        $stringIndexes = [];
        $strings = [];
        $addString = static function (string $value) use (&$stringIndexes, &$strings): int {
            CodecSupport::validateString($value, 256, 'Biome string');
            if (isset($stringIndexes[$value])) {
                return $stringIndexes[$value];
            }
            if (count($strings) >= 65_536) {
                throw new InvalidValueException('Biome string table is oversized.');
            }
            $index = count($strings);
            $stringIndexes[$value] = $index;
            $strings[] = $value;
            return $index;
        };

        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($definitions));
        foreach ($definitions as $definition) {
            if ($definition['id'] < 0 || $definition['id'] > 65_535 || !str_starts_with($definition['name'], 'minecraft:')
                || $definition['map_water_argb'] < 0
                || $definition['map_water_argb'] > 0xffffffff || count($definition['tags']) > 128 || !array_is_list($definition['tags'])) {
                throw new InvalidValueException('Biome definition is outside Bedrock bounds.');
            }
            foreach (['temperature', 'downfall', 'foliage_snow', 'depth', 'scale'] as $field) {
                CodecSupport::validateFiniteFloat($definition[$field], 'Biome numeric field');
            }
            // Vanilla biomes use the -1 sentinel in the modern definition format. Sending
            // legacy numeric IDs here advertises them as custom biomes and makes the retail
            // client attempt to validate custom-biome metadata that Bedriox does not define.
            $writer = $writer->writeUnsignedShortLE($addString($definition['name']))->writeUnsignedShortLE(0xffff)
                ->writeFloatLE($definition['temperature'])->writeFloatLE($definition['downfall'])
                ->writeFloatLE($definition['foliage_snow'])->writeFloatLE($definition['depth'])->writeFloatLE($definition['scale'])
                ->writeUnsignedIntLE($definition['map_water_argb']);
            $writer = CodecSupport::writeBoolean($writer, $definition['rain']);
            $writer = CodecSupport::writeBoolean($writer, true)->writeUnsignedVarInt(count($definition['tags']));
            foreach ($definition['tags'] as $tag) {
                if (!is_string($tag)) {
                    throw new InvalidValueException('Biome tag must be a string.');
                }
                $writer = $writer->writeUnsignedShortLE($addString($tag));
            }
            $writer = CodecSupport::writeBoolean($writer, false); // no optional chunk-generation model in admitted JSON
        }
        $writer = $writer->writeUnsignedVarInt(count($strings));
        foreach ($strings as $string) {
            $writer = $writer->writeString($string, 256);
        }
        return new self($writer->toString());
    }

    public function packetId(): int { return PacketIds::BIOME_DEFINITION_LIST; }
    public function encode(): string { return $this->payload; }
}
