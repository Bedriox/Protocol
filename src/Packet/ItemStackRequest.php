<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class ItemStackRequest
{
    public const int MAXIMUM_ACTIONS = 100;
    public const int MAXIMUM_FILTER_STRINGS = 16;
    public const int MAXIMUM_FILTER_STRING_BYTES = 4_096;

    /**
     * @param list<ItemStackRequestAction> $actions
     * @param list<string> $filterStrings
     */
    public function __construct(
        public int $requestId,
        public array $actions,
        public array $filterStrings = [],
        public ?int $textProcessingOrigin = null,
    ) {
        if ($requestId < -0x80000000 || $requestId > 0x7fffffff) {
            throw new InvalidValueException('Item-stack request ID must fit in 32 bits.');
        }
        CodecSupport::validateCount($actions, self::MAXIMUM_ACTIONS, 'Item-stack request actions');
        foreach ($actions as $action) {
            if (!$action instanceof ItemStackRequestAction) {
                throw new InvalidValueException('Item-stack request actions must be typed values.');
            }
        }
        CodecSupport::validateCount($filterStrings, self::MAXIMUM_FILTER_STRINGS, 'Item-stack request filter strings');
        foreach ($filterStrings as $string) {
            CodecSupport::validateString($string, self::MAXIMUM_FILTER_STRING_BYTES, 'Item-stack request filter string');
        }
        if ($textProcessingOrigin !== null && ($textProcessingOrigin < 0 || $textProcessingOrigin > 16)) {
            throw new InvalidValueException('Item-stack request text-processing origin is unknown.');
        }
    }
}
