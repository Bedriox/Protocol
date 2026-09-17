<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class PlayerAbilities
{
    /** @param list<AbilityLayer> $layers */
    public function __construct(public int $uniqueEntityId, public int $playerPermission, public int $commandPermission, public array $layers)
    {
        if ($playerPermission < 0 || $playerPermission > 3 || $commandPermission < 0 || $commandPermission > 5) {
            throw new InvalidValueException('Player or command permission is outside the Bedrock range.');
        }
        CodecSupport::validateCount($layers, 8, 'Ability layers');
        foreach ($layers as $layer) { if (!$layer instanceof AbilityLayer) { throw new InvalidValueException('Ability layers must be values.'); } }
    }

    public function write(ByteBufferWriter $writer): ByteBufferWriter
    {
        $writer = $writer->writeSignedLongLE($this->uniqueEntityId)->writeUnsignedByte($this->playerPermission)
            ->writeUnsignedByte($this->commandPermission)->writeUnsignedVarInt(count($this->layers));
        foreach ($this->layers as $layer) {
            $writer = $writer->writeUnsignedShortLE($layer->type)->writeSignedIntLE($layer->abilitiesSet)
                ->writeSignedIntLE($layer->abilityValues)->writeFloatLE($layer->flySpeed)
                ->writeFloatLE($layer->verticalFlySpeed)->writeFloatLE($layer->walkSpeed);
        }
        return $writer;
    }

    /** @return array{self, ByteBufferReader} */
    public static function read(ByteBufferReader $reader): array
    {
        $id = $reader->readSignedLongLE(); $player = $id->reader->readUnsignedByte(); $command = $player->reader->readUnsignedByte();
        if ($player->value > 3 || $command->value > 5) { throw new MalformedDataException('Player ability permissions are invalid.'); }
        $count = $command->reader->readUnsignedVarInt();
        if ($count->value > 8) { throw new MalformedDataException('Ability-layer count exceeds its limit.'); }
        $layers = []; $reader = $count->reader;
        for ($i = 0; $i < $count->value; ++$i) {
            $type = $reader->readUnsignedShortLE(); $set = $type->reader->readSignedIntLE(); $values = $set->reader->readSignedIntLE();
            $fly = $values->reader->readFloatLE(); $vertical = $fly->reader->readFloatLE(); $walk = $vertical->reader->readFloatLE();
            if ($type->value > 5) { throw new MalformedDataException('Ability-layer type is invalid.'); }
            CodecSupport::validateFiniteFloat($fly->value, 'Fly speed', true); CodecSupport::validateFiniteFloat($walk->value, 'Walk speed', true);
            CodecSupport::validateFiniteFloat($vertical->value, 'Vertical fly speed', true);
            $layers[] = new AbilityLayer($type->value, $set->value, $values->value, $fly->value, $vertical->value, $walk->value); $reader = $walk->reader;
        }
        return [new self($id->value, $player->value, $command->value, $layers), $reader];
    }
}
