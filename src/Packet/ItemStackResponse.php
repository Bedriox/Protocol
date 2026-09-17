<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ItemStackResponse
{
    public const int STATUS_SUCCESS = 0;
    public const int STATUS_ERROR = 1;
    public const int MAXIMUM_STATUS = 67;
    public const int MAXIMUM_CONTAINERS = 128;

    /** @param list<ItemStackResponseContainer> $containers */
    public function __construct(public int $result, public int $requestId, public array $containers = [])
    {
        if ($result < 0 || $result > self::MAXIMUM_STATUS
            || $requestId < -0x80000000 || $requestId > 0x7fffffff) {
            throw new InvalidValueException('Item-stack response contains an out-of-range scalar.');
        }
        CodecSupport::validateCount($containers, self::MAXIMUM_CONTAINERS, 'Item-stack response containers');
        foreach ($containers as $container) {
            if (!$container instanceof ItemStackResponseContainer) {
                throw new InvalidValueException('Item-stack response containers must be typed values.');
            }
        }
        if ($result !== self::STATUS_SUCCESS && $containers !== []) {
            throw new InvalidValueException('Failed item-stack responses cannot contain mutations.');
        }
    }
}
