<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

interface ItemStackRequestAction
{
    public function typeId(): int;
}
