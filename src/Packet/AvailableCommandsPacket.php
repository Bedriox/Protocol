<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/**
 * Current-protocol command declarations.
 *
 * Bedriox deliberately exposes standard typed parameters here. Enum, postfix,
 * constraint and chained-subcommand tables remain empty until their complete
 * high-level model is introduced, avoiding partially valid client data.
 */
final readonly class AvailableCommandsPacket implements Packet
{
    public const int MAX_COMMANDS = 1_024;
    public const int MAX_OVERLOADS = 64;
    public const int MAX_PARAMETERS = 64;

    /** @param list<CommandDefinition> $commands */
    public function __construct(public array $commands)
    {
        CodecSupport::validateCount($commands, self::MAX_COMMANDS, 'Available commands');
        $seen = [];
        foreach ($commands as $command) {
            if (!$command instanceof CommandDefinition) {
                throw new InvalidValueException('Available commands must be typed definitions.');
            }
            if (isset($seen[$command->name])) {
                throw new InvalidValueException('Available command names must be unique.');
            }
            $seen[$command->name] = true;
        }
    }

    public function packetId(): int { return PacketIds::AVAILABLE_COMMANDS; }

    public function encode(): string
    {
        // enum values, chained values, postfixes, enums and chained-subcommand data
        $writer = CodecSupport::writer()
            ->writeUnsignedVarInt(0)->writeUnsignedVarInt(0)->writeUnsignedVarInt(0)
            ->writeUnsignedVarInt(0)->writeUnsignedVarInt(0)
            ->writeUnsignedVarInt(count($this->commands));
        foreach ($this->commands as $command) {
            $writer = $writer->writeString($command->name, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeString($command->description, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeUnsignedShortLE($command->flags)
                ->writeString($command->permission->value, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeSignedIntLE(-1)
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
                        ->writeUnsignedIntLE(CommandWireCodec::argumentSymbol($parameter->type));
                    $writer = CodecSupport::writeBoolean($writer, $parameter->optional)
                        ->writeUnsignedByte($optionBits);
                }
            }
        }
        // soft enums and enum constraints
        return $writer->writeUnsignedVarInt(0)->writeUnsignedVarInt(0)->toString();
    }

    public static function decode(string $bytes): self
    {
        $reader = CodecSupport::reader($bytes);
        for ($table = 0; $table < 5; ++$table) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value !== 0) {
                throw new MalformedDataException('Unsupported AvailableCommands table is not empty.');
            }
            $reader = $count->reader;
        }
        $commandCount = $reader->readUnsignedVarInt();
        if ($commandCount->value > self::MAX_COMMANDS) {
            throw new MalformedDataException('Available command count exceeds its limit.');
        }
        $reader = $commandCount->reader;
        $commands = [];
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
            if ($alias->value !== -1) {
                throw new MalformedDataException('Command aliases require the enum-table model.');
            }
            $subcommands = $alias->reader->readUnsignedVarInt();
            if ($subcommands->value !== 0) {
                throw new MalformedDataException('Command subcommands require the chained-subcommand model.');
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
                    $options = [];
                    foreach (CommandParameterOption::cases() as $option) {
                        if (($optionBits->value & (1 << $option->value)) !== 0) {
                            $options[] = $option;
                        }
                    }
                    $parameters[] = new CommandParameter(
                        $parameterName->value,
                        CommandWireCodec::argumentType($symbol->value),
                        $optional,
                        $options,
                    );
                    $reader = $optionBits->reader;
                }
                $overloads[] = new CommandOverload($parameters, $chaining);
            }
            $commands[] = new CommandDefinition($name->value, $description->value, $permission, $overloads, $flags->value);
        }
        $softEnums = $reader->readUnsignedVarInt();
        $constraints = $softEnums->reader->readUnsignedVarInt();
        if ($softEnums->value !== 0 || $constraints->value !== 0) {
            throw new MalformedDataException('Unsupported AvailableCommands trailing table is not empty.');
        }
        CodecSupport::requireEnd($constraints->reader);
        return new self($commands);
    }
}
