<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class EnchantOption
{
    public const int MAXIMUM_ENCHANTS_PER_SLOT = 64;

    /** @var list<EnchantData> */
    public array $enchants0;
    /** @var list<EnchantData> */
    public array $enchants1;
    /** @var list<EnchantData> */
    public array $enchants2;

    /**
     * @param list<EnchantData> $enchants0
     * @param list<EnchantData> $enchants1
     * @param list<EnchantData> $enchants2
     */
    public function __construct(
        public int $cost,
        public int $primarySlot,
        array $enchants0,
        array $enchants1,
        array $enchants2,
        public string $name,
        public int $networkId,
    ) {
        if ($cost < 0 || $cost > 0xff || $primarySlot < -0x80000000 || $primarySlot > 0x7fffffff
            || $networkId < 0 || $networkId > 0xffffffff) {
            throw new InvalidValueException('Enchant option contains an out-of-range scalar.');
        }
        CodecSupport::validateString($name, CodecSupport::MAX_SHORT_STRING_BYTES, 'Enchant option name');
        $this->enchants0 = self::validateEnchants($enchants0);
        $this->enchants1 = self::validateEnchants($enchants1);
        $this->enchants2 = self::validateEnchants($enchants2);
    }

    /**
     * @param array<array-key, mixed> $enchants
     * @return list<EnchantData>
     */
    private static function validateEnchants(array $enchants): array
    {
        if (!array_is_list($enchants) || count($enchants) > self::MAXIMUM_ENCHANTS_PER_SLOT) {
            throw new InvalidValueException('Enchant list must be a bounded list.');
        }
        foreach ($enchants as $enchant) {
            if (!$enchant instanceof EnchantData) {
                throw new InvalidValueException('Enchant list contains an invalid value.');
            }
        }
        return $enchants;
    }
}
