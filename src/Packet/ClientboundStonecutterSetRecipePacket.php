<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ClientboundStonecutterSetRecipePacket implements Packet
{
    public function __construct(public int $playerUniqueEntityId, public int $containerId, public int $recipeIndex)
    {
        if ($containerId < 0 || $containerId > 255) {
            throw new InvalidValueException('Stonecutter container ID must fit an unsigned byte.');
        }
    }

    public function packetId(): int { return PacketIds::CLIENTBOUND_STONECUTTER_SET_RECIPE; }

    public function encode(): string
    {
        return CodecSupport::writer()->writeSignedVarLong($this->playerUniqueEntityId)
            ->writeUnsignedByte($this->containerId)->writeSignedVarInt($this->recipeIndex)->toString();
    }

    public static function decode(string $bytes): self
    {
        $player = CodecSupport::reader($bytes)->readSignedVarLong();
        $container = $player->reader->readUnsignedByte();
        $recipe = $container->reader->readSignedVarInt();
        CodecSupport::requireEnd($recipe->reader);
        try {
            return new self($player->value, $container->value, $recipe->value);
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Stonecutter recipe update is invalid.', previous: $error);
        }
    }
}
