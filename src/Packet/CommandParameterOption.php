<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

enum CommandParameterOption: int
{
    case SuppressEnumAutocomplete = 0;
    case HasSemanticConstraint = 1;
    case EnumAsChainedCommand = 2;
}
