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

    /** @param list<CraftingRecipe> $recipes */
    public function __construct(array $recipes = [], public bool $cleanRecipes = true)
    {
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

        // Deferred workstation and chemistry-mixing registries remain empty in this packet surface.
        $writer = $writer->writeUnsignedVarInt(0)->writeUnsignedVarInt(0)
            ->writeUnsignedVarInt(0)->writeUnsignedVarInt(0)->writeUnsignedVarInt(0);
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
        for ($section = 0; $section < 5; ++$section) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value !== 0) {
                throw new MalformedDataException('Unsupported crafting-data section must be empty.');
            }
            $reader = $count->reader;
        }
        [$cleanRecipes, $reader] = CodecSupport::readBoolean($reader);
        CodecSupport::requireEnd($reader);
        try {
            return new self($recipes, $cleanRecipes);
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
}
