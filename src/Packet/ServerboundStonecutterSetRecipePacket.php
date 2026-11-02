<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ServerboundStonecutterSetRecipePacket implements Packet
{
    public function __construct(public int $containerId, public int $recipeIndex)
    {
        if ($containerId < 0 || $containerId > 255) {
            throw new InvalidValueException('Stonecutter container ID must fit an unsigned byte.');
        }
    }

    public function packetId(): int { return PacketIds::SERVERBOUND_STONECUTTER_SET_RECIPE; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeUnsignedByte($this->containerId)->writeSignedVarInt($this->recipeIndex)->toString();
    }

    public static function decode(string $bytes): self
    {
        $container = CodecSupport::reader($bytes)->readUnsignedByte();
        $recipe = $container->reader->readSignedVarInt();
        CodecSupport::requireEnd($recipe->reader);
        try {
            return new self($container->value, $recipe->value);
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Stonecutter recipe selection is invalid.', previous: $error);
        }
    }
}
