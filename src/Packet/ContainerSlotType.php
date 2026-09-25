<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Current protocol-2193 full-container-name domain used by stack requests and corrections. */
enum ContainerSlotType: int
{
    case AnvilInput = 0;
    case AnvilMaterial = 1;
    case AnvilResult = 2;
    case SmithingTableInput = 3;
    case SmithingTableMaterial = 4;
    case SmithingTableResult = 5;
    case Armor = 6;
    case LevelEntity = 7;
    case BeaconPayment = 8;
    case BrewingInput = 9;
    case BrewingResult = 10;
    case BrewingFuel = 11;
    case HotbarAndInventory = 12;
    case CraftingInput = 13;
    case CraftingOutput = 14;
    case RecipeConstruction = 15;
    case RecipeNature = 16;
    case RecipeItems = 17;
    case RecipeSearch = 18;
    case RecipeSearchBar = 19;
    case RecipeEquipment = 20;
    case RecipeBook = 21;
    case EnchantingInput = 22;
    case EnchantingMaterial = 23;
    case FurnaceFuel = 24;
    case FurnaceIngredient = 25;
    case FurnaceResult = 26;
    case HorseEquipment = 27;
    case Hotbar = 28;
    case Inventory = 29;
    case ShulkerBox = 30;
    case TradeIngredientOne = 31;
    case TradeIngredientTwo = 32;
    case TradeResult = 33;
    case Offhand = 34;
    case CompoundCreatorInput = 35;
    case CompoundCreatorOutput = 36;
    case ElementConstructorOutput = 37;
    case MaterialReducerInput = 38;
    case MaterialReducerOutput = 39;
    case LabTableInput = 40;
    case LoomInput = 41;
    case LoomDye = 42;
    case LoomMaterial = 43;
    case LoomResult = 44;
    case BlastFurnaceIngredient = 45;
    case SmokerIngredient = 46;
    case TradeTwoIngredientOne = 47;
    case TradeTwoIngredientTwo = 48;
    case TradeTwoResult = 49;
    case GrindstoneInput = 50;
    case GrindstoneAdditional = 51;
    case GrindstoneResult = 52;
    case StonecutterInput = 53;
    case StonecutterResult = 54;
    case CartographyInput = 55;
    case CartographyAdditional = 56;
    case CartographyResult = 57;
    case Barrel = 58;
    case Cursor = 59;
    case CreatedOutput = 60;
    case SmithingTableTemplate = 61;
    case CrafterBlockContainer = 62;
    case DynamicContainer = 63;
    case RecipeFoodContainer = 64;
    case RecipeBlocksContainer = 65;
    case RecipeFurnaceItemsContainer = 66;
}
