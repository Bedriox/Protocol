<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\MalformedDataException;

/** Current protocol action discriminators and their duplicated wire markers. */
enum ItemStackRequestActionType: int
{
    case Take = 0;
    case Place = 1;
    case Swap = 2;
    case Drop = 3;
    case Destroy = 4;
    case Consume = 5;
    case Create = 6;
    case LabTableCombine = 7;
    case BeaconPayment = 8;
    case MineBlock = 9;
    case CraftRecipe = 10;
    case AutoCraftRecipe = 11;
    case CraftCreative = 12;
    case CraftRecipeOptional = 13;
    case CraftRepairAndDisenchant = 14;
    case CraftLoom = 15;
    case CraftNonImplemented = 16;
    case CraftResults = 17;

    public function marker(): int
    {
        return match ($this) {
            self::LabTableCombine => 9,
            self::BeaconPayment => 10,
            self::MineBlock => 11,
            self::CraftRecipe => 12,
            self::AutoCraftRecipe => 13,
            self::CraftCreative => 14,
            self::CraftRecipeOptional => 15,
            self::CraftRepairAndDisenchant => 16,
            self::CraftLoom => 17,
            self::CraftNonImplemented => 18,
            self::CraftResults => 19,
            default => $this->value,
        };
    }

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value)
            ?? throw new MalformedDataException('Item-stack request action type is unsupported.');
    }
}
