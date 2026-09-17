<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

interface InventoryTransactionData
{
    public function type(): InventoryTransactionType;
}
