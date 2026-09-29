<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class PlayerEnchantOptionsPacket implements Packet
{
    public const int MAXIMUM_OPTIONS = 3;

    /** @var list<EnchantOption> */
    public array $options;

    /** @param list<EnchantOption> $options */
    public function __construct(array $options)
    {
        if (!array_is_list($options) || count($options) > self::MAXIMUM_OPTIONS) {
            throw new InvalidValueException('Enchant options must be a bounded list.');
        }
        foreach ($options as $option) {
            if (!$option instanceof EnchantOption) {
                throw new InvalidValueException('Enchant options contain an invalid value.');
            }
        }
        $this->options = $options;
    }

    public function packetId(): int { return PacketIds::PLAYER_ENCHANT_OPTIONS; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarInt(count($this->options));
        foreach ($this->options as $option) {
            $writer = $writer->writeUnsignedByte($option->cost)->writeSignedIntLE($option->primarySlot);
            foreach ([$option->enchants0, $option->enchants1, $option->enchants2] as $enchants) {
                $writer = self::writeEnchants($writer, $enchants);
            }
            $writer = $writer->writeString($option->name, CodecSupport::MAX_SHORT_STRING_BYTES)
                ->writeUnsignedVarInt($option->networkId);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $count = CodecSupport::reader($bytes)->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_OPTIONS) {
            throw new MalformedDataException('Enchant option count exceeds its limit.');
        }
        $reader = $count->reader;
        $options = [];
        for ($index = 0; $index < $count->value; ++$index) {
            $cost = $reader->readUnsignedByte();
            $primarySlot = $cost->reader->readSignedIntLE();
            [$enchants0, $reader] = self::readEnchants($primarySlot->reader);
            [$enchants1, $reader] = self::readEnchants($reader);
            [$enchants2, $reader] = self::readEnchants($reader);
            $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $networkId = $name->reader->readUnsignedVarInt();
            try {
                $options[] = new EnchantOption(
                    $cost->value, $primarySlot->value, $enchants0, $enchants1, $enchants2,
                    $name->value, $networkId->value,
                );
            } catch (InvalidValueException $e) {
                throw new MalformedDataException('Enchant option is invalid.', previous: $e);
            }
            $reader = $networkId->reader;
        }
        CodecSupport::requireEnd($reader);
        return new self($options);
    }

    /** @param list<EnchantData> $enchants */
    private static function writeEnchants(ByteBufferWriter $writer, array $enchants): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt(count($enchants));
        foreach ($enchants as $enchant) {
            $writer = $writer->writeUnsignedVarInt($enchant->type)->writeUnsignedByte($enchant->level);
        }
        return $writer;
    }

    /** @return array{list<EnchantData>, ByteBufferReader} */
    private static function readEnchants(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > EnchantOption::MAXIMUM_ENCHANTS_PER_SLOT) {
            throw new MalformedDataException('Enchant list count exceeds its limit.');
        }
        $reader = $count->reader;
        $enchants = [];
        for ($index = 0; $index < $count->value; ++$index) {
            $type = $reader->readUnsignedVarInt();
            $level = $type->reader->readUnsignedByte();
            $enchants[] = new EnchantData($type->value, $level->value);
            $reader = $level->reader;
        }
        return [$enchants, $reader];
    }
}
