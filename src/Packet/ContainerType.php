<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum ContainerType: int
{
    case None = -9;
    case Inventory = -1;
    case Container = 0;
    case Workbench = 1;
    case Furnace = 2;
    case Enchantment = 3;
    case BrewingStand = 4;
    case Anvil = 5;
    case Dispenser = 6;
    case Dropper = 7;
    case Hopper = 8;
    case Cauldron = 9;
    case MinecartChest = 10;
    case MinecartHopper = 11;
    case Horse = 12;
    case Beacon = 13;
    case StructureEditor = 14;
    case Trade = 15;
    case CommandBlock = 16;
    case Jukebox = 17;
    case Armor = 18;
    case Hand = 19;
    case CompoundCreator = 20;
    case ElementConstructor = 21;
    case MaterialReducer = 22;
    case LabTable = 23;
    case Loom = 24;
    case Lectern = 25;
    case Grindstone = 26;
    case BlastFurnace = 27;
    case Smoker = 28;
    case Stonecutter = 29;
    case Cartography = 30;
    case Hud = 31;
    case JigsawEditor = 32;
    case SmithingTable = 33;
    case ChestBoat = 34;
    case DecoratedPot = 35;
    case Crafter = 36;
}
