<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CreateItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = 6;

    public function __construct(public int $slot)
    {
        if ($slot < 0 || $slot > 0xff) {
            throw new InvalidValueException('Created-output slot is outside the unsigned-byte range.');
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
