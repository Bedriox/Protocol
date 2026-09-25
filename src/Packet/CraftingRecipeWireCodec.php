<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Codec\ByteBufferReader;
use Bedriox\Protocol\Codec\ByteBufferWriter;
use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Internal bounded protocol-2193 recipe wire codec. */
final class CraftingRecipeWireCodec
{
    /** @return array{CraftingRecipeIngredient, ByteBufferReader} */
    public static function readIngredient(ByteBufferReader $reader): array
    {
        $outerType = $reader->readUnsignedVarInt();
        if ($outerType->value > 1) {
            throw new MalformedDataException('Crafting ingredient outer descriptor type is unsupported.');
        }
        $reader = $outerType->reader;
        $type = CraftingRecipeIngredientType::Empty;
        $value = null;
        $auxOrVersion = 0;
        if ($outerType->value === 0) {
            $sentinel = $reader->readSignedVarInt();
            if ($sentinel->value !== 0x7fff) {
                throw new MalformedDataException('Empty crafting ingredient has an invalid sentinel.');
            }
            $reader = $sentinel->reader;
        } else {
            $name = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            $type = CraftingRecipeIngredientType::fromWireName($name->value);
            $descriptor = $name->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            if ($descriptor->value === '') {
                throw new MalformedDataException('Crafting ingredient descriptor cannot be empty.');
            }
            $value = $descriptor->value;
            $reader = $descriptor->reader;
            if ($type === CraftingRecipeIngredientType::Molang) {
                $version = $reader->readSignedShortLE();
                $auxOrVersion = $version->value;
                $reader = $version->reader;
            } else {
                $aux = $reader->readSignedVarInt();
                $auxOrVersion = $type === CraftingRecipeIngredientType::Item ? $aux->value : 0;
                if ($type === CraftingRecipeIngredientType::ItemTag && $aux->value !== 0x7fff) {
                    throw new MalformedDataException('Tag crafting ingredient has an invalid sentinel.');
                }
                $reader = $aux->reader;
            }
        }
        $count = $reader->readSignedVarInt();
        try {
            return [new CraftingRecipeIngredient($type, $value, $auxOrVersion, $count->value), $count->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Crafting ingredient is invalid.', previous: $e);
        }
    }

    public static function writeIngredient(ByteBufferWriter $writer, CraftingRecipeIngredient $ingredient): ByteBufferWriter
    {
        $outerType = $ingredient->type === CraftingRecipeIngredientType::Empty ? 0 : 1;
        $writer = $writer->writeUnsignedVarInt($outerType);
        if ($ingredient->type === CraftingRecipeIngredientType::Empty) {
            return $writer->writeSignedVarInt(0x7fff)->writeSignedVarInt(0);
        }
        $wireName = $ingredient->type->wireName();
        if ($wireName === null || $ingredient->value === null) {
            throw new InvalidValueException('Crafting ingredient descriptor is invalid.');
        }
        $writer = $writer->writeString($wireName, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeString($ingredient->value, CodecSupport::MAX_SHORT_STRING_BYTES);
        if ($ingredient->type === CraftingRecipeIngredientType::Item) {
            $writer = $writer->writeSignedVarInt($ingredient->auxOrVersion);
        } elseif ($ingredient->type === CraftingRecipeIngredientType::Molang) {
            $writer = $writer->writeSignedShortLE($ingredient->auxOrVersion);
        } else {
            $writer = $writer->writeSignedVarInt(0x7fff);
        }
        return $writer->writeSignedVarInt($ingredient->count);
    }

    /** Current auto-craft actions use the duplicated descriptor marker form. */
    public static function writeActionIngredient(ByteBufferWriter $writer, CraftingRecipeIngredient $ingredient): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt($ingredient->type->value)
            ->writeUnsignedByte($ingredient->type->value);
        if ($ingredient->value !== null) {
            $writer = $writer->writeString($ingredient->value, CodecSupport::MAX_SHORT_STRING_BYTES);
            $writer = match ($ingredient->type) {
                CraftingRecipeIngredientType::Item => $writer->writeSignedVarInt($ingredient->auxOrVersion),
                CraftingRecipeIngredientType::Molang => $writer->writeSignedShortLE($ingredient->auxOrVersion),
                CraftingRecipeIngredientType::ItemTag => $writer,
                CraftingRecipeIngredientType::Empty => throw new InvalidValueException('Unexpected empty action ingredient.'),
            };
        }
        if ($ingredient->count > 0xffff) {
            throw new InvalidValueException('Automatic crafting ingredient count exceeds its wire range.');
        }
        return $writer->writeUnsignedShortLE($ingredient->count);
    }

    /** @return array{CraftingRecipeIngredient, ByteBufferReader} */
    public static function readActionIngredient(ByteBufferReader $reader): array
    {
        $type = $reader->readUnsignedVarInt();
        $descriptorType = CraftingRecipeIngredientType::tryFrom($type->value);
        if ($descriptorType === null) {
            throw new MalformedDataException('Automatic crafting ingredient type is unsupported.');
        }
        $marker = $type->reader->readUnsignedByte();
        if ($marker->value !== $descriptorType->value) {
            throw new MalformedDataException('Automatic crafting ingredient type markers disagree.');
        }
        $reader = $marker->reader;
        $value = null;
        $auxOrVersion = 0;
        if ($descriptorType !== CraftingRecipeIngredientType::Empty) {
            $descriptor = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
            if ($descriptor->value === '') {
                throw new MalformedDataException('Automatic crafting ingredient descriptor cannot be empty.');
            }
            $value = $descriptor->value;
            $reader = $descriptor->reader;
            if ($descriptorType === CraftingRecipeIngredientType::Item) {
                $aux = $reader->readSignedVarInt();
                $auxOrVersion = $aux->value;
                $reader = $aux->reader;
            } elseif ($descriptorType === CraftingRecipeIngredientType::Molang) {
                $version = $reader->readSignedShortLE();
                $auxOrVersion = $version->value;
                $reader = $version->reader;
            }
        }
        $count = $reader->readUnsignedShortLE();
        try {
            return [new CraftingRecipeIngredient($descriptorType, $value, $auxOrVersion, $count->value), $count->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Automatic crafting ingredient is invalid.', previous: $e);
        }
    }

    public static function writeShaped(ByteBufferWriter $writer, ShapedCraftingRecipe $recipe): ByteBufferWriter
    {
        $writer = $writer->writeString($recipe->recipeId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedVarInt($recipe->width)
            ->writeSignedVarInt($recipe->height)
            ->writeUnsignedVarInt(count($recipe->ingredients));
        foreach ($recipe->ingredients as $ingredient) {
            $writer = self::writeIngredient($writer, $ingredient);
        }
        $writer = self::writeResults($writer, $recipe->results)
            ->writeBytes(CodecSupport::uuidToWire($recipe->uuid))
            ->writeString($recipe->craftingTag, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedVarInt($recipe->priority);
        $writer = CodecSupport::writeBoolean($writer, $recipe->assumeSymmetry);
        $hasRequirement = $recipe->type() === CraftingRecipeType::Shaped;
        $writer = CodecSupport::writeBoolean($writer, $hasRequirement);
        if ($hasRequirement) {
            $writer = self::writeRequirement($writer, $recipe->unlockRequirement);
        }
        return $writer->writeUnsignedVarInt($recipe->networkId());
    }

    /** @return array{ShapedCraftingRecipe, ByteBufferReader} */
    public static function readShaped(ByteBufferReader $reader, CraftingRecipeType $type): array
    {
        $id = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $width = $id->reader->readSignedVarInt();
        $height = $width->reader->readSignedVarInt();
        if ($width->value < 1 || $width->value > 3 || $height->value < 1 || $height->value > 3) {
            throw new MalformedDataException('Shaped crafting recipe dimensions are invalid.');
        }
        [$ingredients, $reader] = self::readIngredients($height->reader, $width->value * $height->value, true);
        [$results, $reader] = self::readResults($reader);
        $uuid = $reader->readBytes(16);
        $tag = $uuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $priority = $tag->reader->readSignedVarInt();
        [$symmetry, $reader] = CodecSupport::readBoolean($priority->reader);
        [$hasRequirement, $reader] = CodecSupport::readBoolean($reader);
        $expectedRequirement = $type === CraftingRecipeType::Shaped;
        if ($hasRequirement !== $expectedRequirement) {
            throw new MalformedDataException('Shaped crafting recipe requirement marker is invalid.');
        }
        $requirement = CraftingRecipeUnlockRequirement::none();
        if ($hasRequirement) {
            [$requirement, $reader] = self::readRequirement($reader);
        }
        $networkId = $reader->readUnsignedVarInt();
        try {
            return [new ShapedCraftingRecipe(
                $type, $id->value, $width->value, $height->value, $ingredients, $results,
                CodecSupport::uuidFromWire($uuid->value), $tag->value, $priority->value, $symmetry,
                $requirement, $networkId->value,
            ), $networkId->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Shaped crafting recipe is invalid.', previous: $e);
        }
    }

    public static function writeShapeless(ByteBufferWriter $writer, ShapelessCraftingRecipe $recipe): ByteBufferWriter
    {
        $writer = $writer->writeString($recipe->recipeId, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeUnsignedVarInt(count($recipe->ingredients));
        foreach ($recipe->ingredients as $ingredient) {
            $writer = self::writeIngredient($writer, $ingredient);
        }
        $writer = self::writeResults($writer, $recipe->results)
            ->writeBytes(CodecSupport::uuidToWire($recipe->uuid))
            ->writeString($recipe->craftingTag, CodecSupport::MAX_SHORT_STRING_BYTES)
            ->writeSignedVarInt($recipe->priority);
        $hasRequirement = in_array($recipe->type(), [CraftingRecipeType::Shapeless, CraftingRecipeType::UserDataShapeless], true);
        $writer = CodecSupport::writeBoolean($writer, $hasRequirement);
        if ($hasRequirement) {
            $writer = self::writeRequirement($writer, $recipe->unlockRequirement);
        }
        return $writer->writeUnsignedVarInt($recipe->networkId());
    }

    /** @return array{ShapelessCraftingRecipe, ByteBufferReader} */
    public static function readShapeless(ByteBufferReader $reader, CraftingRecipeType $type): array
    {
        $id = $reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$ingredients, $reader] = self::readIngredients($id->reader, ShapelessCraftingRecipe::MAXIMUM_INGREDIENTS);
        [$results, $reader] = self::readResults($reader);
        $uuid = $reader->readBytes(16);
        $tag = $uuid->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $priority = $tag->reader->readSignedVarInt();
        [$hasRequirement, $reader] = CodecSupport::readBoolean($priority->reader);
        $expectedRequirement = in_array($type, [CraftingRecipeType::Shapeless, CraftingRecipeType::UserDataShapeless], true);
        if ($hasRequirement !== $expectedRequirement) {
            throw new MalformedDataException('Shapeless crafting recipe requirement marker is invalid.');
        }
        $requirement = CraftingRecipeUnlockRequirement::none();
        if ($hasRequirement) {
            [$requirement, $reader] = self::readRequirement($reader);
        }
        $networkId = $reader->readUnsignedVarInt();
        try {
            return [new ShapelessCraftingRecipe(
                $type, $id->value, $ingredients, $results, CodecSupport::uuidFromWire($uuid->value),
                $tag->value, $priority->value, $requirement, $networkId->value,
            ), $networkId->reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Shapeless crafting recipe is invalid.', previous: $e);
        }
    }

    /** @param list<InventoryItemStack> $results */
    private static function writeResults(ByteBufferWriter $writer, array $results): ByteBufferWriter
    {
        $writer = $writer->writeUnsignedVarInt(count($results));
        foreach ($results as $result) {
            $writer = CreativeItemStackWireCodec::write($writer, $result);
        }
        return $writer;
    }

    /** @return array{list<InventoryItemStack>, ByteBufferReader} */
    private static function readResults(ByteBufferReader $reader): array
    {
        $count = $reader->readUnsignedVarInt();
        if ($count->value < 1 || $count->value > ShapedCraftingRecipe::MAXIMUM_RESULTS) {
            throw new MalformedDataException('Crafting recipe result count is invalid.');
        }
        $results = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            [$results[], $reader] = CreativeItemStackWireCodec::read($reader);
        }
        return [$results, $reader];
    }

    /** @return array{list<CraftingRecipeIngredient>, ByteBufferReader} */
    private static function readIngredients(ByteBufferReader $reader, int $maximum, bool $exact = false): array
    {
        $count = $reader->readUnsignedVarInt();
        if (($exact && $count->value !== $maximum) || (!$exact && ($count->value < 1 || $count->value > $maximum))) {
            throw new MalformedDataException('Crafting recipe ingredient count is invalid.');
        }
        $ingredients = [];
        $reader = $count->reader;
        for ($index = 0; $index < $count->value; ++$index) {
            [$ingredients[], $reader] = self::readIngredient($reader);
        }
        return [$ingredients, $reader];
    }

    private static function writeRequirement(ByteBufferWriter $writer, CraftingRecipeUnlockRequirement $requirement): ByteBufferWriter
    {
        $writer = $writer->writeSignedVarInt($requirement->context->value);
        $hasIngredients = $requirement->context === RecipeUnlockingContext::None;
        $writer = CodecSupport::writeBoolean($writer, $hasIngredients);
        if (!$hasIngredients) {
            return $writer;
        }
        $writer = $writer->writeUnsignedVarInt(count($requirement->ingredients));
        foreach ($requirement->ingredients as $ingredient) {
            $writer = self::writeIngredient($writer, $ingredient);
        }
        return $writer;
    }

    /** @return array{CraftingRecipeUnlockRequirement, ByteBufferReader} */
    private static function readRequirement(ByteBufferReader $reader): array
    {
        $context = $reader->readSignedVarInt();
        $unlockingContext = RecipeUnlockingContext::tryFrom($context->value);
        if ($unlockingContext === null) {
            throw new MalformedDataException('Crafting recipe unlocking context is unsupported.');
        }
        [$hasIngredients, $reader] = CodecSupport::readBoolean($context->reader);
        if ($hasIngredients !== ($unlockingContext === RecipeUnlockingContext::None)) {
            throw new MalformedDataException('Crafting recipe unlocking ingredient marker is invalid.');
        }
        $ingredients = [];
        if ($hasIngredients) {
            $count = $reader->readUnsignedVarInt();
            if ($count->value > CraftingRecipeUnlockRequirement::MAXIMUM_INGREDIENTS) {
                throw new MalformedDataException('Crafting recipe unlocking ingredient count exceeds its limit.');
            }
            $reader = $count->reader;
            for ($index = 0; $index < $count->value; ++$index) {
                [$ingredients[], $reader] = self::readIngredient($reader);
            }
        }
        try {
            return [new CraftingRecipeUnlockRequirement($unlockingContext, $ingredients), $reader];
        } catch (InvalidValueException $e) {
            throw new MalformedDataException('Crafting recipe unlock requirement is invalid.', previous: $e);
        }
    }
}
