<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Exception;

use Throwable;

/** Safe, bounded location of a failed item-stack request decode; never contains packet bytes. */
final class ItemStackRequestDecodeException extends MalformedDataException
{
    public function __construct(
        public readonly string $stage,
        public readonly string $detailCode,
        public readonly int $byteOffset,
        public readonly ?int $actionIndex,
        public readonly ?int $actionType,
        Throwable $previous,
    ) {
        parent::__construct('Item-stack request could not be decoded.', previous: $previous);
    }
}
