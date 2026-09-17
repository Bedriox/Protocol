<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Codec;

/** @template-covariant T */
final readonly class ReadResult
{
    /** @param T $value */
    public function __construct(
        public mixed $value,
        public ByteBufferReader $reader,
    ) {
    }
}
