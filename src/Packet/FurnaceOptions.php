<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

final readonly class FurnaceOptions
{
    public function __construct(
        public FurnaceLeftTab $leftTab,
        public bool $filtering,
        public FurnaceLayout $layout,
    ) {}
}
