<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;

final readonly class ClientboundMatchmakingStatePacket implements Packet
{
    public function __construct(
        public MatchmakingState $state,
        public string $destinationName,
        public ?MatchmakingStateOptions $options = null,
    ) {
        CodecSupport::validateString($destinationName, CodecSupport::MAX_SHORT_STRING_BYTES, 'Matchmaking destination name');
    }

    public function packetId(): int
    {
        return PacketIds::CLIENTBOUND_MATCHMAKING_STATE;
    }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedByte($this->state->value)
            ->writeString($this->destinationName, CodecSupport::MAX_SHORT_STRING_BYTES);
        $writer = CodecSupport::writeBoolean($writer, $this->options !== null);
        if ($this->options === null) {
            return $writer->toString();
        }
        $writer = CodecSupport::writeBoolean($writer, $this->options->triggeringPlayerName !== null);
        if ($this->options->triggeringPlayerName !== null) {
            $writer = $writer->writeString($this->options->triggeringPlayerName, CodecSupport::MAX_PLAYER_NAME_BYTES);
        }
        $writer = CodecSupport::writeBoolean($writer, $this->options->triggeredByLocalPlayer !== null);
        if ($this->options->triggeredByLocalPlayer !== null) {
            $writer = CodecSupport::writeBoolean($writer, $this->options->triggeredByLocalPlayer);
        }
        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $state = CodecSupport::reader($bytes)->readUnsignedByte();
        $matchmakingState = MatchmakingState::tryFrom($state->value)
            ?? throw new MalformedDataException('Matchmaking state is unknown.');
        $destination = $state->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        [$hasOptions, $reader] = CodecSupport::readBoolean($destination->reader);
        $options = null;
        if ($hasOptions) {
            [$hasName, $reader] = CodecSupport::readBoolean($reader);
            $name = null;
            if ($hasName) {
                $value = $reader->readString(CodecSupport::MAX_PLAYER_NAME_BYTES);
                $name = $value->value;
                $reader = $value->reader;
            }
            [$hasLocalFlag, $reader] = CodecSupport::readBoolean($reader);
            $local = null;
            if ($hasLocalFlag) {
                [$local, $reader] = CodecSupport::readBoolean($reader);
            }
            $options = new MatchmakingStateOptions($name, $local);
        }
        CodecSupport::requireEnd($reader);
        try {
            return new self($matchmakingState, $destination->value, $options);
        } catch (InvalidValueException $error) {
            throw new MalformedDataException('Matchmaking state payload is invalid.', previous: $error);
        }
    }
}
