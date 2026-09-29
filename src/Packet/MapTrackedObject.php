<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class MapTrackedObject
{
    private function __construct(
        public MapTrackedObjectType $type,
        public ?int $entityId,
        public ?BlockPosition $position,
    ) {
        if (($type === MapTrackedObjectType::Entity) !== ($entityId !== null)
            || ($type === MapTrackedObjectType::Block) !== ($position !== null)) {
            throw new InvalidValueException('Map tracked object value does not match its type.');
        }
    }

    public static function entity(int $entityId): self
    {
        return new self(MapTrackedObjectType::Entity, $entityId, null);
    }

    public static function block(BlockPosition $position): self
    {
        return new self(MapTrackedObjectType::Block, null, $position);
    }
}
