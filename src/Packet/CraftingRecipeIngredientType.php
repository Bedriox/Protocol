<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

enum CraftingRecipeIngredientType: int
{
    case Empty = 0;
    case Item = 1;
    case Molang = 2;
    case ItemTag = 3;

    public function wireName(): ?string
    {
        return match ($this) {
            self::Empty => null,
            self::Item => 'name',
            self::Molang => 'molang',
            self::ItemTag => 'item_tag',
        };
    }

    public static function fromWireName(string $name): self
    {
        return match ($name) {
            'name' => self::Item,
            'molang' => self::Molang,
            'item_tag' => self::ItemTag,
            default => throw new MalformedDataException('Crafting ingredient descriptor name is unsupported.'),
        };
    }
}
