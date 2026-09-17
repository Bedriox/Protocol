<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;

/** One bounded server-authoritative block action carried by PlayerAuthInput. */
final readonly class PlayerBlockAction
{
    public function __construct(
        public PlayerActionType $action,
        public ?BlockPosition $position = null,
        public ?int $face = null,
    ) {
        if (!in_array($action, self::supportedActions(), true)) {
            throw new InvalidValueException('PlayerAuthInput block action type is not permitted in this field.');
        }
        $stopsWithoutTarget = $action === PlayerActionType::StopDestroyBlock;
        if ($stopsWithoutTarget !== ($position === null && $face === null)) {
            throw new InvalidValueException('PlayerAuthInput block action target presence is invalid.');
        }
        if ($face !== null && ($face < -0x80000000 || $face > 0x7fffffff)) {
            throw new InvalidValueException('PlayerAuthInput block action face must fit a signed 32-bit integer.');
        }
    }

    /** @return list<PlayerActionType> */
    private static function supportedActions(): array
    {
        return [
            PlayerActionType::StartDestroyBlock,
            PlayerActionType::AbortDestroyBlock,
            PlayerActionType::StopDestroyBlock,
            PlayerActionType::CrackBlock,
            PlayerActionType::PredictDestroyBlock,
            PlayerActionType::ContinueDestroyBlock,
        ];
    }
}
