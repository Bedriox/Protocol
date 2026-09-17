<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class BasicInventoryTransaction implements InventoryTransactionData
{
    public function __construct(private InventoryTransactionType $transactionType)
    {
        if ($transactionType !== InventoryTransactionType::Normal && $transactionType !== InventoryTransactionType::InventoryMismatch) {
            throw new InvalidValueException('Basic inventory transaction type must be normal or mismatch.');
        }
    }

    public function type(): InventoryTransactionType
    {
        return $this->transactionType;
    }
}
