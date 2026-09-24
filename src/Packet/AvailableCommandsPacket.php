<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Current-protocol command declarations and their shared enum tables. */
final readonly class AvailableCommandsPacket implements Packet
{
    public const int MAX_COMMANDS = 1_024;
    public const int MAX_OVERLOADS = 64;
    public const int MAX_PARAMETERS = 64;
    public const int MAX_ENUMS = 1_024;
    public const int MAX_ENUM_VALUES = 4_096;

    /** @param list<CommandDefinition> $commands */
    public function __construct(public array $commands)
    {
        CodecSupport::validateCount($commands, self::MAX_COMMANDS, 'Available commands');
        $names = [];
        foreach ($commands as $command) {
            if (!$command instanceof CommandDefinition) {
                throw new InvalidValueException('Available commands must be typed definitions.');
            }
            if (isset($names[$command->name])) {
                throw new InvalidValueException('Available command names must be unique.');
            }
            $names[$command->name] = true;
        }
        foreach ($commands as $command) {
            foreach ($command->aliases as $alias) {
                if ($alias === $command->name) {
                    continue;
                }
                if (isset($names[$alias])) {
                    throw new InvalidValueException('Command names and aliases must be globally unique.');
                }
                $names[$alias] = true;
            }
        }
        self::collectEnums($commands);
    }

    public function packetId(): int
    {
        return PacketIds::AVAILABLE_COMMANDS;
    }

    public function encode(): string
    {
        [$enumValues, $hardEnums, $softEnums, $aliasIndexes] = self::collectEnums($this->commands);

        $writer = self::writeStrings(CodecSupport::writer(), $enumValues)
            ->writeUnsignedVarInt(0)
            ->writeUnsignedVarInt(0);
        $writer = $writer->writeUnsignedVarInt(count($hardEnums));
        foreach ($hardEnums as $enum) {
            $writer = $writer->writeString($enum->name, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeUnsignedVarInt(count($enum->values));
            foreach ($enum->values as $value) {
                $index = array_search($value, $enumValues, true);
                if (!is_int($index)) {
                    throw new InvalidValueException('Hard enum value is absent from the shared value table.');
                }
                $writer = $writer->writeUnsignedIntLE($index);
            }
        }

        $writer = $writer->writeUnsignedVarInt(0)
            ->writeUnsignedVarInt(count($this->commands));
        foreach ($this->commands as $commandIndex => $command) {
            $writer = $writer->writeString($command->name, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeString($command->description, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeUnsignedShortLE($command->flags)
                ->writeString($command->permission->value, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeSignedIntLE($aliasIndexes[$commandIndex])
                ->writeUnsignedVarInt(0)
                ->writeUnsignedVarInt(count($command->overloads));
            foreach ($command->overloads as $overload) {
                $writer = CodecSupport::writeBoolean($writer, $overload->chaining)
                    ->writeUnsignedVarInt(count($overload->parameters));
                foreach ($overload->parameters as $parameter) {
                    $optionBits = 0;
                    foreach ($parameter->options as $option) {
                        $optionBits |= 1 << $option->value;
                    }
                    $writer = $writer->writeString($parameter->name, CodecSupport::MAX_SHORT_STRING_BYTES)
                        ->writeUnsignedIntLE(CommandWireCodec::parameterSymbol($parameter->type, $hardEnums, $softEnums));
                    $writer = CodecSupport::writeBoolean($writer, $parameter->optional)
                        ->writeUnsignedByte($optionBits);
                }
            }
        }

        $writer = $writer->writeUnsignedVarInt(count($softEnums));
        foreach ($softEnums as $enum) {
            $writer = $writer->writeString($enum->name, CodecSupport::MAX_SHORT_STRING_BYTES);
            $writer = self::writeStrings($writer, $enum->values);
        }
        return $writer->writeUnsignedVarInt(0)->toString();
    }

    public static function decode(string $bytes): self
    {
        $reader = CodecSupport::reader($bytes);
        [$enumValues, $reader] = self::readStrings($reader, self::MAX_ENUM_VALUES, 'Command enum value');

        $chainedValues = $reader->readUnsignedVarInt();
        if ($chainedValues->value !== 0) {
            throw new MalformedDataException('Chained command values are unsupported.');
        }
        $postfixes = $chainedValues->reader->readUnsignedVarInt();
        if ($postfixes->value !== 0) {
            throw new MalformedDataException('Command postfixes are unsupported.');
        }

        $hardCount = $postfixes->reader->readUnsignedVarInt();
        if ($hardCount->value > self::MAX_ENUMS) {
            throw new MalformedDataException('Hard command enum count exceeds its limit.');
        }
        $reader = $hardCount->reader;
        $hardEnums = [];
        $enumNames = [];
        for ($index = 0; $index < $hardCount->value; ++$index) {
            $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            if (isset($enumNames[$name->value])) {
                throw new MalformedDataException('Command enum names must be unique.');
            }
            $enumNames[$name->value] = true;
            $valueCount = $name->reader->readUnsignedVarInt();
            if ($valueCount->value > self::MAX_ENUM_VALUES) {
                throw new MalformedDataException('Hard command enum value count exceeds its limit.');
            }
            $values = [];
            $reader = $valueCount->reader;
            for ($valueIndex = 0; $valueIndex < $valueCount->value; ++$valueIndex) {
                $sharedIndex = $reader->readUnsignedIntLE();
                if (!isset($enumValues[$sharedIndex->value])) {
                    throw new MalformedDataException('Hard command enum references an unknown value.');
                }
                $values[] = $enumValues[$sharedIndex->value];
                $reader = $sharedIndex->reader;
            }
            $hardEnums[] = self::enumFromWire($name->value, $values, false);
        }

        $chainedData = $reader->readUnsignedVarInt();
        if ($chainedData->value !== 0) {
            throw new MalformedDataException('Chained command data is unsupported.');
        }
        $commandCount = $chainedData->reader->readUnsignedVarInt();
        if ($commandCount->value > self::MAX_COMMANDS) {
            throw new MalformedDataException('Available command count exceeds its limit.');
        }
        $reader = $commandCount->reader;
        $records = [];
        for ($commandIndex = 0; $commandIndex < $commandCount->value; ++$commandIndex) {
            $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $description = $name->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $flags = $description->reader->readUnsignedShortLE();
            $permissionWire = $flags->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $permission = CommandPermission::tryFrom($permissionWire->value);
            if ($permission === null) {
                throw new MalformedDataException('Command permission is unknown.');
            }
            $alias = $permissionWire->reader->readSignedIntLE();
            if ($alias->value < -1 || $alias->value >= count($hardEnums)) {
                throw new MalformedDataException('Command alias enum index is invalid.');
            }
            $subcommands = $alias->reader->readUnsignedVarInt();
            if ($subcommands->value !== 0) {
                throw new MalformedDataException('Chained command indexes are unsupported.');
            }
            $overloadCount = $subcommands->reader->readUnsignedVarInt();
            if ($overloadCount->value > self::MAX_OVERLOADS) {
                throw new MalformedDataException('Command overload count exceeds its limit.');
            }
            $reader = $overloadCount->reader;
            $overloads = [];
            for ($overloadIndex = 0; $overloadIndex < $overloadCount->value; ++$overloadIndex) {
                [$chaining, $reader] = CodecSupport::readBoolean($reader);
                $parameterCount = $reader->readUnsignedVarInt();
                if ($parameterCount->value > self::MAX_PARAMETERS) {
                    throw new MalformedDataException('Command parameter count exceeds its limit.');
                }
                $reader = $parameterCount->reader;
                $parameters = [];
                for ($parameterIndex = 0; $parameterIndex < $parameterCount->value; ++$parameterIndex) {
                    $parameterName = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
                    $symbol = $parameterName->reader->readUnsignedIntLE();
                    [$optional, $reader] = CodecSupport::readBoolean($symbol->reader);
                    $optionBits = $reader->readUnsignedByte();
                    if (($optionBits->value & ~0x07) !== 0) {
                        throw new MalformedDataException('Command parameter options contain unknown bits.');
                    }
                    $parameters[] = [$parameterName->value, $symbol->value, $optional, self::options($optionBits->value)];
                    $reader = $optionBits->reader;
                }
                $overloads[] = [$parameters, $chaining];
            }
            $records[] = [$name->value, $description->value, $permission, $overloads, $flags->value, $alias->value];
        }

        $softCount = $reader->readUnsignedVarInt();
        if ($softCount->value > self::MAX_ENUMS) {
            throw new MalformedDataException('Soft command enum count exceeds its limit.');
        }
        $reader = $softCount->reader;
        $softEnums = [];
        for ($index = 0; $index < $softCount->value; ++$index) {
            $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            if (isset($enumNames[$name->value])) {
                throw new MalformedDataException('Command enum names must be unique.');
            }
            $enumNames[$name->value] = true;
            [$values, $reader] = self::readStrings($name->reader, self::MAX_ENUM_VALUES, 'Soft command enum value');
            $softEnums[] = self::enumFromWire($name->value, $values, true);
        }
        $constraints = $reader->readUnsignedVarInt();
        if ($constraints->value !== 0) {
            throw new MalformedDataException('Command enum constraints are unsupported.');
        }
        CodecSupport::requireEnd($constraints->reader);

        try {
            $commands = [];
            foreach ($records as [$name, $description, $permission, $overloadRecords, $flags, $aliasIndex]) {
                $overloads = [];
                foreach ($overloadRecords as [$parameterRecords, $chaining]) {
                    $parameters = [];
                    foreach ($parameterRecords as [$parameterName, $symbol, $optional, $options]) {
                        $parameters[] = new CommandParameter(
                            $parameterName,
                            CommandWireCodec::parameterType($symbol, $hardEnums, $softEnums),
                            $optional,
                            $options,
                        );
                    }
                    $overloads[] = new CommandOverload($parameters, $chaining);
                }
                $aliases = $aliasIndex === -1 ? [] : $hardEnums[$aliasIndex]->values;
                $commands[] = new CommandDefinition($name, $description, $permission, $overloads, $flags, $aliases);
            }
            return new self($commands);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException('Available commands contain an invalid semantic model.', previous: $exception);
        }
    }

    /**
     * @param list<CommandDefinition> $commands
     * @return array{list<string>, list<CommandEnum>, list<CommandEnum>, list<int>}
     */
    private static function collectEnums(array $commands): array
    {
        $enumValues = [];
        $hardEnums = [];
        $softEnums = [];
        $known = [];
        $aliasIndexes = [];
        foreach ($commands as $command) {
            $aliasIndexes[] = $command->aliases === []
                ? -1
                : self::addEnum(new CommandEnum('bedriox:aliases:' . $command->name, $command->aliases), $hardEnums, $softEnums, $known, $enumValues);
            foreach ($command->overloads as $overload) {
                foreach ($overload->parameters as $parameter) {
                    if ($parameter->type instanceof CommandEnum) {
                        self::addEnum($parameter->type, $hardEnums, $softEnums, $known, $enumValues);
                    }
                }
            }
        }
        return [$enumValues, $hardEnums, $softEnums, $aliasIndexes];
    }

    /**
     * @param list<CommandEnum> $hardEnums
     * @param list<CommandEnum> $softEnums
     * @param array<string, CommandEnum> $known
     * @param list<string> $enumValues
     */
    private static function addEnum(CommandEnum $enum, array &$hardEnums, array &$softEnums, array &$known, array &$enumValues): int
    {
        if (isset($known[$enum->name])) {
            if ($known[$enum->name] != $enum) {
                throw new InvalidValueException('Command enum names must identify one consistent enum.');
            }
            $list = $enum->soft ? $softEnums : $hardEnums;
            $index = array_search($known[$enum->name], $list, true);
            if (!is_int($index)) {
                throw new InvalidValueException('Command enum registry is inconsistent.');
            }
            return $index;
        }
        $known[$enum->name] = $enum;
        if ($enum->soft) {
            if (count($softEnums) >= self::MAX_ENUMS) {
                throw new InvalidValueException('Soft command enum count exceeds its limit.');
            }
            $softEnums[] = $enum;
            return count($softEnums) - 1;
        }
        if (count($hardEnums) >= self::MAX_ENUMS) {
            throw new InvalidValueException('Hard command enum count exceeds its limit.');
        }
        $hardEnums[] = $enum;
        foreach ($enum->values as $value) {
            if (!in_array($value, $enumValues, true)) {
                if (count($enumValues) >= self::MAX_ENUM_VALUES) {
                    throw new InvalidValueException('Shared hard enum values exceed their limit.');
                }
                $enumValues[] = $value;
            }
        }
        return count($hardEnums) - 1;
    }

    /** @param list<string> $values */
    private static function writeStrings(ByteBufferWriter $writer, array $values): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt(count($values));
        foreach ($values as $value) {
            $writer = $writer->writeString($value, CodecSupport::MAX_SHORT_STRING_BYTES);
        }
        return $writer;
    }

    /** @return array{list<string>, ByteBufferReader} */
    private static function readStrings(ByteBufferReader $reader, int $maximum, string $field): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > $maximum) {
            throw new MalformedDataException($field . ' count exceeds its limit.');
        }
        $values = [];
        $seen = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $value = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            if ($value->value === '' || isset($seen[$value->value])) {
                throw new MalformedDataException($field . ' values must be non-empty and unique.');
            }
            $values[] = $value->value;
            $seen[$value->value] = true;
            $reader = $value->reader;
        }
        return [$values, $reader];
    }

    /** @return list<CommandParameterOption> */
    private static function options(int $bits): array
    {
        $options = [];
        foreach (CommandParameterOption::cases() as $option) {
            if (($bits & (1 << $option->value)) !== 0) {
                $options[] = $option;
            }
        }
        return $options;
    }

    /** @param list<string> $values */
    private static function enumFromWire(string $name, array $values, bool $soft): CommandEnum
    {
        try {
            return new CommandEnum($name, $values, $soft);
        } catch (InvalidValueException $exception) {
            throw new MalformedDataException('Command enum is invalid.', previous: $exception);
        }
    }
}
