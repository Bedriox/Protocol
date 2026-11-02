<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftReservedItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::Reserved->value;

    public function __construct(public string $reservedId, public int $numberOfCrafts)
    {
        CodecSupport::validateString($reservedId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Reserved craft ID');
        if ($numberOfCrafts < 0 || $numberOfCrafts > 255) {
            throw new InvalidValueException('Reserved craft count must fit an unsigned byte.');
        }
    }

    public function typeId(): int
    {
        return self::TYPE_ID;
    }
}
