<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** Deprecated client craft-result report; never authorizes inventory mutation. */
final readonly class CraftResultsItemStackRequestAction implements ItemStackRequestAction
{
    public const int TYPE_ID = ItemStackRequestActionType::CraftResults->value;
    public const int MAXIMUM_RESULTS = 16;

    /** @param list<ItemStackRequestResultItem> $results */
    public function __construct(public array $results, public int $numberOfCrafts)
    {
        if (!array_is_list($results) || count($results) > self::MAXIMUM_RESULTS
            || $numberOfCrafts < 0 || $numberOfCrafts > 0xff) {
            throw new InvalidValueException('Craft-results action is invalid.');
        }
        foreach ($results as $result) {
            if (!$result instanceof ItemStackRequestResultItem) {
                throw new InvalidValueException('Craft-results action contains an invalid item.');
            }
        }
    }

    public function typeId(): int { return self::TYPE_ID; }
}
