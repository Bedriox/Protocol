<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

/** Current Bedrock actor lifecycle animation with its optional fire origin. */
final readonly class ActorEventPacket implements Packet
{
    public function __construct(
        public UnsignedLong $runtimeEntityId,
        public ActorEventType $event,
        public int $data = 0,
        public ?float $fireX = null,
        public ?float $fireY = null,
        public ?float $fireZ = null,
    ) {
        if ($this->data < -0x80000000 || $this->data > 0x7fffffff) {
            throw new InvalidValueException('Actor-event data must fit in a signed 32-bit integer.');
        }
        $present = [$this->fireX !== null, $this->fireY !== null, $this->fireZ !== null];
        if (count(array_unique($present)) !== 1) {
            throw new InvalidValueException('Actor-event fire coordinates must be all present or all absent.');
        }
        foreach ([$this->fireX, $this->fireY, $this->fireZ] as $coordinate) {
            if ($coordinate !== null) {
                CodecSupport::validateFiniteFloat($coordinate, 'Actor-event fire coordinate');
            }
        }
    }

    public function packetId(): int { return PacketIds::ACTOR_EVENT; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeUnsignedByte($this->event->value)
            ->writeSignedVarInt($this->data);
        $writer = CodecSupport::writeBoolean($writer, $this->fireX !== null);
        if ($this->fireX !== null && $this->fireY !== null && $this->fireZ !== null) {
            $writer = $writer->writeFloatLE($this->fireX)->writeFloatLE($this->fireY)->writeFloatLE($this->fireZ);
        }

        return $writer->toString();
    }

    public static function decode(string $bytes): self
    {
        $runtime = CodecSupport::reader($bytes)->readUnsignedVarLong();
        $event = $runtime->reader->readUnsignedByte();
        $type = ActorEventType::tryFrom($event->value);
        if ($type === null) {
            throw new MalformedDataException('Actor-event type is invalid for the current protocol.');
        }
        $data = $event->reader->readSignedVarInt();
        [$hasFirePosition, $reader] = CodecSupport::readBoolean($data->reader);
        $coordinates = [null, null, null];
        if ($hasFirePosition) {
            foreach ([0, 1, 2] as $index) {
                $value = $reader->readFloatLE();
                CodecSupport::validateFiniteFloat($value->value, 'Actor-event fire coordinate', true);
                $coordinates[$index] = $value->value;
                $reader = $value->reader;
            }
        }
        CodecSupport::requireEnd($reader);

        return new self($runtime->value, $type, $data->value, ...$coordinates);
    }
}
