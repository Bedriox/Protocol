<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;

final readonly class GameRuleSet
{
    /** @param list<GameRule> $rules */
    public function __construct(public array $rules)
    {
        if (!array_is_list($rules) || count($rules) > 128) {
            throw new InvalidValueException('Game-rule collection is not a bounded list.');
        }
        $names = [];
        foreach ($rules as $rule) {
            if (!$rule instanceof GameRule || isset($names[$rule->name])) {
                throw new InvalidValueException('Game rules must be typed values with unique names.');
            }
            $names[$rule->name] = true;
        }
    }

    public static function survivalDefaults(): self
    {
        $boolean = static fn(string $name, bool $value): GameRule => new GameRule($name, GameRule::BOOLEAN, $value);
        $integer = static fn(string $name, int $value): GameRule => new GameRule($name, GameRule::INTEGER, $value);

        return new self([
            $boolean('commandblocksenabled', false),
            $boolean('commandblockoutput', true),
            $boolean('dodaylightcycle', true),
            $boolean('doentitydrops', true),
            $boolean('dofiretick', true),
            $boolean('doinsomnia', false),
            $boolean('doimmediaterespawn', false),
            $boolean('dolimitedcrafting', false),
            $boolean('domobloot', true),
            $boolean('domobspawning', true),
            $boolean('dotiledrops', true),
            $boolean('doweathercycle', true),
            $boolean('drowningdamage', true),
            $boolean('falldamage', true),
            $boolean('firedamage', true),
            $boolean('freezedamage', true),
            $integer('functioncommandlimit', 10_000),
            $boolean('keepinventory', false),
            $boolean('locatorbar', true),
            $integer('maxcommandchainlength', 65_536),
            $boolean('mobgriefing', true),
            $boolean('naturalregeneration', true),
            $integer('playerssleepingpercentage', 100),
            $boolean('projectilescanbreakblocks', true),
            $boolean('pvp', true),
            $integer('randomtickspeed', 3),
            $boolean('recipesunlock', false),
            $boolean('respawnblocksexplode', true),
            $boolean('showbordereffect', true),
            $boolean('sendcommandfeedback', true),
            $boolean('showcoordinates', false),
            $boolean('showdaysplayed', false),
            $boolean('showdeathmessages', true),
            $boolean('showrecipemessages', true),
            $boolean('showtags', true),
            $integer('spawnradius', 10),
            $boolean('tntexplodes', true),
            $boolean('tntexplosiondropdecay', false),
        ]);
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->rules));
        foreach ($this->rules as $rule) {
            $writer = $writer->writeString($rule->name, 64);
            $writer = CodecSupport::writeBoolean($writer, $rule->editable)->writeUnsignedVarInt($rule->type);
            $writer = self::writeValue($writer, $rule);
        }

        return $writer->toString();
    }

    private static function writeValue(ByteBufferWriter $writer, GameRule $rule): ByteBufferWriter
    {
        return match ($rule->type) {
            GameRule::BOOLEAN => CodecSupport::writeBoolean($writer, (bool) $rule->value),
            GameRule::INTEGER => $writer->writeSignedIntLE((int) $rule->value),
            GameRule::FLOAT => $writer->writeFloatLE((float) $rule->value),
            default => throw new \LogicException('Validated game-rule type is unreachable.'),
        };
    }
}
