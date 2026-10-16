<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

/** Bounded clientbound protocol-2193 villager trade-window snapshot. */
final readonly class UpdateTradePacket implements Packet
{
    public const int MAXIMUM_DISPLAY_NAME_BYTES = 256;
    public const int MAXIMUM_OFFERS_NBT_BYTES = 262_144;

    public function __construct(
        public int $containerId,
        public ContainerType $containerType,
        public int $size,
        public int $tradeTier,
        public int $traderUniqueEntityId,
        public int $playerUniqueEntityId,
        public string $displayName,
        public bool $newTradingUi,
        public bool $usingEconomyTrade,
        public string $offersNetworkNbt,
    ) {
        if ($containerId < 0 || $containerId > 0xff || $containerType !== ContainerType::Trade) {
            throw new InvalidValueException('Update-trade container fields are outside their supported domain.');
        }
        if ($size !== 0 || $tradeTier < 0 || $tradeTier > 4) {
            throw new InvalidValueException('Update-trade size or tier is outside its supported domain.');
        }
        CodecSupport::validateString($displayName, self::MAXIMUM_DISPLAY_NAME_BYTES, 'Trade display name');
        if (strlen($offersNetworkNbt) > self::MAXIMUM_OFFERS_NBT_BYTES) {
            throw new InvalidValueException('Trade offers NBT exceeds its packet limit.');
        }
        NetworkNbtCompound::validate($offersNetworkNbt);
    }

    public static function fromLittleEndianOffers(
        int $containerId,
        int $tradeTier,
        int $traderUniqueEntityId,
        int $playerUniqueEntityId,
        string $displayName,
        string $offersLittleEndianNbt,
        bool $newTradingUi = true,
        bool $usingEconomyTrade = true,
    ): self {
        return new self(
            $containerId,
            ContainerType::Trade,
            0,
            $tradeTier,
            $traderUniqueEntityId,
            $playerUniqueEntityId,
            $displayName,
            $newTradingUi,
            $usingEconomyTrade,
            LittleEndianNbtToNetwork::convert($offersLittleEndianNbt),
        );
    }

    public function packetId(): int
    {
        return PacketIds::UPDATE_TRADE;
    }

    public function encode(): string
    {
        return CodecSupport::writeBoolean(
            CodecSupport::writeBoolean(
                CodecSupport::writer()
                    ->writeUnsignedByte($this->containerId)
                    ->writeUnsignedByte($this->containerType->value)
                    ->writeSignedVarInt($this->size)
                    ->writeSignedVarInt($this->tradeTier)
                    ->writeSignedVarLong($this->traderUniqueEntityId)
                    ->writeSignedVarLong($this->playerUniqueEntityId)
                    ->writeString($this->displayName, self::MAXIMUM_DISPLAY_NAME_BYTES),
                $this->newTradingUi,
            ),
            $this->usingEconomyTrade,
        )->writeBytes($this->offersNetworkNbt)->toString();
    }

    public static function decode(string $bytes): self
    {
        $containerId = CodecSupport::reader($bytes)->readUnsignedByte();
        $containerType = $containerId->reader->readUnsignedByte();
        if ($containerType->value !== ContainerType::Trade->value) {
            throw new MalformedDataException('Update-trade container type is unsupported.');
        }
        $size = $containerType->reader->readSignedVarInt();
        $tradeTier = $size->reader->readSignedVarInt();
        $traderUniqueEntityId = $tradeTier->reader->readSignedVarLong();
        $playerUniqueEntityId = $traderUniqueEntityId->reader->readSignedVarLong();
        $displayName = $playerUniqueEntityId->reader->readString(self::MAXIMUM_DISPLAY_NAME_BYTES);
        [$newTradingUi, $reader] = CodecSupport::readBoolean($displayName->reader);
        [$usingEconomyTrade, $reader] = CodecSupport::readBoolean($reader);
        $offers = $reader->readBytes($reader->remaining());

        try {
            return new self(
                $containerId->value,
                ContainerType::Trade,
                $size->value,
                $tradeTier->value,
                $traderUniqueEntityId->value,
                $playerUniqueEntityId->value,
                $displayName->value,
                $newTradingUi,
                $usingEconomyTrade,
                $offers->value,
            );
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Update-trade payload is invalid.', previous: $error);
        }
    }
}
