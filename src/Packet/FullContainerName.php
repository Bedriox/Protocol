<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Current FullContainerName wire value; container-name IDs are defined by protocol 2193. */
final readonly class FullContainerName
{
    public const int ARMOR = 6;
    public const int COMBINED_HOTBAR_AND_INVENTORY = 12;
    public const int CRAFTING_INPUT = 13;
    public const int CRAFTING_OUTPUT = 14;
    public const int HOTBAR = 28;
    public const int INVENTORY = 29;
    public const int OFFHAND = 34;
    public const int HORSE_EQUIPMENT = 27;
    public const int CURSOR = 59;
    public const int CREATED_OUTPUT = 60;
    public const int LEVEL_ENTITY = 7;
    public const int SHULKER_BOX = 30;
    public const int BARREL = 58;
    public const int DYNAMIC = 63;
    public const int MAXIMUM_CONTAINER_NAME_ID = 66;

    public int $containerNameId;

    public function __construct(int|ContainerSlotType $containerName = ContainerSlotType::AnvilInput, public ?int $dynamicId = null)
    {
        $containerNameId = $containerName instanceof ContainerSlotType ? $containerName->value : $containerName;
        if (ContainerSlotType::tryFrom($containerNameId) === null
            || ($dynamicId !== null && ($dynamicId < 0 || $dynamicId > 0xffffffff))) {
            throw new InvalidValueException('Full container name contains an out-of-range value.');
        }
        $this->containerNameId = $containerNameId;
    }

    public function slotType(): ContainerSlotType
    {
        return ContainerSlotType::from($this->containerNameId);
    }
}
