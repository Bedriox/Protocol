<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Stable names for the current Bedrock command-argument wire identifiers. */
enum CommandArgumentType: int
{
    case Integer = 1;
    case Float = 3;
    case Value = 4;
    case WildcardInteger = 5;
    case Operator = 6;
    case CompareOperator = 7;
    case Target = 8;
    case WildcardTarget = 10;
    case FilePath = 17;
    case IntegerRange = 23;
    case EquipmentSlot = 47;
    case String = 56;
    case BlockPosition = 64;
    case Position = 65;
    case Message = 68;
    case RawText = 70;
    case Json = 74;
    case BlockStates = 84;
    case Command = 90;
}
