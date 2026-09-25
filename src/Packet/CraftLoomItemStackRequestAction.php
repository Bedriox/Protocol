<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class CraftLoomItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::CraftLoom->value;

    public function __construct(public string $patternId, public int $timesCrafted)
    {
        CodecSupport::validateString($patternId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Loom pattern ID');
        if ($patternId === '' || $timesCrafted < 0 || $timesCrafted > 0xff) {
            throw new InvalidValueException('Loom-craft action contains an invalid value.');
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
