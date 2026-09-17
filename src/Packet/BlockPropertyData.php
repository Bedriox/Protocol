<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One bounded data-driven block definition for StartGame. */
final readonly class BlockPropertyData
{
    private const int MAX_NETWORK_NBT_BYTES = 262_144;

    private function __construct(
        public string $name,
        public string $networkNbt,
    ) {
        if (preg_match('/^[a-z0-9_.-]+:[a-z0-9_.-]+$/D', $name) !== 1) {
            throw new InvalidValueException('Block-property name must be a namespaced identifier.');
        }
        if ($networkNbt === '' || strlen($networkNbt) > self::MAX_NETWORK_NBT_BYTES || ord($networkNbt[0]) !== 10) {
            throw new InvalidValueException('Block-property network NBT is empty, oversized, or not a root compound.');
        }
    }

    public static function fromLittleEndianNbt(string $name, string $littleEndianNbt): self
    {
        return new self($name, LittleEndianNbtToNetwork::convert($littleEndianNbt));
    }

}
