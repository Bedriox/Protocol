<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftingRecipeIngredient
{
    public function __construct(
        public CraftingRecipeIngredientType $type,
        public ?string $value,
        public int $auxOrVersion,
        public int $count,
    ) {
        if (($type === CraftingRecipeIngredientType::Empty) !== ($value === null)) {
            throw new InvalidValueException('Crafting ingredient descriptor value does not match its type.');
        }
        if ($value !== null) {
            CodecSupport::validateString($value, CodecSupport::MAX_SHORT_STRING_BYTES, 'Crafting ingredient descriptor');
            if ($value === '') {
                throw new InvalidValueException('Crafting ingredient descriptor cannot be empty.');
            }
        }
        if ($auxOrVersion < -0x80000000 || $auxOrVersion > 0x7fffffff
            || ($type === CraftingRecipeIngredientType::Molang && ($auxOrVersion < -0x8000 || $auxOrVersion > 0x7fff))
            || $count < 0 || $count > 0x7fffffff
            || ($type === CraftingRecipeIngredientType::Empty && ($auxOrVersion !== 0 || $count !== 0))
            || ($type !== CraftingRecipeIngredientType::Empty && $count === 0)
            || ($type === CraftingRecipeIngredientType::ItemTag && $auxOrVersion !== 0)) {
            throw new InvalidValueException('Crafting ingredient contains an out-of-range value.');
        }
    }

    public static function empty(): self
    {
        return new self(CraftingRecipeIngredientType::Empty, null, 0, 0);
    }

    public static function item(string $identifier, int $count = 1, int $aux = 0x7fff): self
    {
        return new self(CraftingRecipeIngredientType::Item, $identifier, $aux, $count);
    }

    public static function itemTag(string $tag, int $count = 1): self
    {
        return new self(CraftingRecipeIngredientType::ItemTag, $tag, 0, $count);
    }
}
