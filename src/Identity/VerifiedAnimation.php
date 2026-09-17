<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Identity;

final readonly class VerifiedAnimation
{
    public function __construct(
        public int $width,
        public int $height,
        public string $image,
        public int $type,
        public float $frames,
        public int $expressionType = 0,
    ) {
    }
}
