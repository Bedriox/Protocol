<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

/** Fixed player-inventory window IDs in the current Bedrock protocol. */
final class InventoryContainerId
{
    public const int INVENTORY = 0;
    public const int OFFHAND = 119;
    public const int ARMOR = 120;
    public const int HOTBAR = 122;
    public const int FIXED_INVENTORY = 123;
    public const int UI = 124;
    public const int REGISTRY = 125;

    private function __construct()
    {
    }
}
