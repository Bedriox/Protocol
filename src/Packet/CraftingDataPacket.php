<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** A bounded replacement snapshot of the current crafting recipe registry. */
final readonly class CraftingDataPacket implements Packet
{
    public const int MAXIMUM_RECIPES = 16_384;

    /** @var list<CraftingRecipe> */
    public array $recipes;

    /** @var list<PotionMixData> */
    public array $potionMixData;

    /** @var list<ContainerMixData> */
    public array $containerMixData;

    /**
     * @param list<CraftingRecipe> $recipes
     * @param list<PotionMixData> $potionMixData
     * @param list<ContainerMixData> $containerMixData
     */
    public function __construct(
        array $recipes = [],
        public bool $cleanRecipes = true,
        array $potionMixData = [],
        array $containerMixData = [],
    ) {
        if (!array_is_list($recipes) || count($recipes) > self::MAXIMUM_RECIPES) {
            throw new InvalidValueException('Crafting recipe registry must be a bounded list.');
        }
        $networkIds = [];
        foreach ($recipes as $recipe) {
            if (!$recipe instanceof CraftingRecipe) {
                throw new InvalidValueException('Crafting recipe registry contains an invalid recipe.');
            }
            if (isset($networkIds[$recipe->networkId()])) {
                throw new InvalidValueException('Crafting recipe network IDs must be unique.');
            }
            $networkIds[$recipe->networkId()] = true;
        }
        $ordered = [];
        foreach (self::recipeSections() as $type) {
            foreach ($recipes as $recipe) {
                if ($recipe->type() === $type) {
                    $ordered[] = $recipe;
                }
            }
        }
        $this->recipes = $ordered;
        $this->potionMixData = self::validateMixData($potionMixData, PotionMixData::class, 'Potion-mix registry');
        $this->containerMixData = self::validateMixData(
            $containerMixData,
            ContainerMixData::class,
            'Container-mix registry',
        );
    }

    public function packetId(): int { return PacketIds::CRAFTING_DATA; }

    public function encode(): string
    {
        $writer = CodecSupport::writer();
        foreach (self::recipeSections() as $type) {
            $recipes = array_values(array_filter(
                $this->recipes,
                static fn (CraftingRecipe $recipe): bool => $recipe->type() === $type,
            ));
            $writer = $writer->writeUnsignedVarInt(count($recipes));
            foreach ($recipes as $recipe) {
                $writer = self::writeRecipe($writer, $recipe);
            }
        }

        // Smithing transform and trim registries remain deferred.
        $writer = $writer->writeUnsignedVarInt(0)->writeUnsignedVarInt(0);
        $writer = self::writePotionMixData($writer, $this->potionMixData);
        $writer = self::writeContainerMixData($writer, $this->containerMixData);
        // Material reducers remain deferred.
        $writer = $writer->writeUnsignedVarInt(0);
        return CodecSupport::writeBoolean($writer, $this->cleanRecipes)->toString();
    }

    public static function decode(string $bytes): self
    {
        $reader = CodecSupport::reader($bytes);
        $recipes = [];
        foreach (self::recipeSections() as $type) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value > self::MAXIMUM_RECIPES - count($recipes)) {
                throw new MalformedDataException('Crafting recipe count exceeds its limit.');
            }
            $reader = $count->reader;
            for ($index = 0; $index < $count->value; ++$index) {
                [$recipes[], $reader] = self::readRecipe($reader, $type);
            }
        }
        for ($section = 0; $section < 2; ++$section) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value !== 0) {
                throw new MalformedDataException('Unsupported crafting-data section must be empty.');
            }
            $reader = $count->reader;
        }
        [$potionMixData, $reader] = self::readPotionMixData($reader);
        [$containerMixData, $reader] = self::readContainerMixData($reader);
        $materialReducers = $reader->readUnsignedVarInt();
        if ($materialReducers->value !== 0) {
            throw new MalformedDataException('Unsupported crafting-data section must be empty.');
        }
        $reader = $materialReducers->reader;
        [$cleanRecipes, $reader] = CodecSupport::readBoolean($reader);
        CodecSupport::requireEnd($reader);
        try {
            return new self($recipes, $cleanRecipes, $potionMixData, $containerMixData);
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Crafting recipe registry is invalid.', previous: $e);
        }
    }

    /** @return list<CraftingRecipeType> */
    private static function recipeSections(): array
    {
        return [
            CraftingRecipeType::Shaped,
            CraftingRecipeType::Shapeless,
            CraftingRecipeType::Multi,
            CraftingRecipeType::UserDataShapeless,
            CraftingRecipeType::ShapelessChemistry,
            CraftingRecipeType::ShapedChemistry,
        ];
    }

    private static function writeRecipe(ByteBufferWriter $writer, CraftingRecipe $recipe): ByteBufferWriter
    {
        if ($recipe instanceof ShapedCraftingRecipe) {
            return CraftingRecipeWireCodec::writeShaped($writer, $recipe);
        }
        if ($recipe instanceof ShapelessCraftingRecipe) {
            return CraftingRecipeWireCodec::writeShapeless($writer, $recipe);
        }
        if ($recipe instanceof MultiCraftingRecipe) {
            return $writer->writeBytes(CodecSupport::uuidToWire($recipe->uuid))
                ->writeUnsignedVarInt($recipe->networkId());
        }
        throw new InvalidValueException('Crafting recipe type is unsupported.');
    }

    /** @return array{CraftingRecipe, ByteBufferReader} */
    private static function readRecipe(ByteBufferReader $reader, CraftingRecipeType $type): array
    {
        if (in_array($type, [CraftingRecipeType::Shaped, CraftingRecipeType::ShapedChemistry], true)) {
            return CraftingRecipeWireCodec::readShaped($reader, $type);
        }
        if (in_array($type, [
            CraftingRecipeType::Shapeless,
            CraftingRecipeType::UserDataShapeless,
            CraftingRecipeType::ShapelessChemistry,
        ], true)) {
            return CraftingRecipeWireCodec::readShapeless($reader, $type);
        }
        $uuid = $reader->readBytes(16);
        $networkId = $uuid->reader->readUnsignedVarInt();
        try {
            return [new MultiCraftingRecipe(
                CodecSupport::uuidFromWire($uuid->value),
                $networkId->value,
            ), $networkId->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Multi crafting recipe is invalid.', previous: $e);
        }
    }

    /**
     * @template T of object
     * @param array<array-key, mixed> $values
     * @param class-string<T> $type
     * @return list<T>
     */
    private static function validateMixData(array $values, string $type, string $field): array
    {
        if (!array_is_list($values) || count($values) > self::MAXIMUM_RECIPES) {
            throw new InvalidValueException($field . ' must be a bounded list.');
        }
        foreach ($values as $value) {
            if (!$value instanceof $type) {
                throw new InvalidValueException($field . ' contains an invalid entry.');
            }
        }
        /** @var list<T> $values */
        return $values;
    }

    /** @param list<PotionMixData> $entries */
    private static function writePotionMixData(ByteBufferWriter $writer, array $entries): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt(count($entries));
        foreach ($entries as $entry) {
            $writer = $writer
                ->writeSignedVarInt($entry->inputItemId)
                ->writeSignedVarInt($entry->inputAuxiliaryValue)
                ->writeSignedVarInt($entry->reagentItemId)
                ->writeSignedVarInt($entry->reagentAuxiliaryValue)
                ->writeSignedVarInt($entry->outputItemId)
                ->writeSignedVarInt($entry->outputAuxiliaryValue);
        }
        return $writer;
    }

    /** @return array{list<PotionMixData>, ByteBufferReader} */
    private static function readPotionMixData(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_RECIPES) {
            throw new MalformedDataException('Potion-mix registry exceeds its limit.');
        }
        $entries = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $inputId = $reader->readSignedVarInt();
            $inputAux = $inputId->reader->readSignedVarInt();
            $reagentId = $inputAux->reader->readSignedVarInt();
            $reagentAux = $reagentId->reader->readSignedVarInt();
            $outputId = $reagentAux->reader->readSignedVarInt();
            $outputAux = $outputId->reader->readSignedVarInt();
            $entries[] = new PotionMixData(
                $inputId->value,
                $inputAux->value,
                $reagentId->value,
                $reagentAux->value,
                $outputId->value,
                $outputAux->value,
            );
            $reader = $outputAux->reader;
        }
        return [$entries, $reader];
    }

    /** @param list<ContainerMixData> $entries */
    private static function writeContainerMixData(ByteBufferWriter $writer, array $entries): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt(count($entries));
        foreach ($entries as $entry) {
            $writer = $writer
                ->writeSignedVarInt($entry->inputItemId)
                ->writeSignedVarInt($entry->reagentItemId)
                ->writeSignedVarInt($entry->outputItemId);
        }
        return $writer;
    }

    /** @return array{list<ContainerMixData>, ByteBufferReader} */
    private static function readContainerMixData(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value > self::MAXIMUM_RECIPES) {
            throw new MalformedDataException('Container-mix registry exceeds its limit.');
        }
        $entries = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            $inputId = $reader->readSignedVarInt();
            $reagentId = $inputId->reader->readSignedVarInt();
            $outputId = $reagentId->reader->readSignedVarInt();
            $entries[] = new ContainerMixData($inputId->value, $reagentId->value, $outputId->value);
            $reader = $outputId->reader;
        }
        return [$entries, $reader];
    }
}
