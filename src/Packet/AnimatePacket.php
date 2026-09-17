<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;

final readonly class AnimatePacket implements Packet
{
    public const int SWING = 1;
    /** @var list<int> */
    private const array ACTIONS = [0, self::SWING, 3, 4, 5, 128, 129];

    public function __construct(public int $action, public UnsignedLong $runtimeEntityId, public float $data, public ?string $swingSource)
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new InvalidValueException('Animation action is unknown.');
        }
        CodecSupport::validateFiniteFloat($data, 'Animation data');
        if ($swingSource !== null && !in_array($swingSource, ['none', 'build', 'mine', 'interact', 'attack', 'useitem', 'throwitem', 'dropitem', 'event'], true)) {
            throw new InvalidValueException('Animation swing source is unknown.');
        }
    }
    public function packetId(): int { return PacketIds::ANIMATE; }
    public function isSwing(): bool { return $this->action === self::SWING; }
    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeUnsignedByte($this->action)->writeUnsignedVarLong($this->runtimeEntityId)->writeFloatLE($this->data);
        $writer = CodecSupport::writeBoolean($writer, $this->swingSource !== null);
        return ($this->swingSource === null ? $writer : $writer->writeString($this->swingSource, 16))->toString();
    }
    public static function decode(string $bytes): self
    {
        $action = CodecSupport::reader($bytes)->readUnsignedByte();
        if (!in_array($action->value, self::ACTIONS, true)) {
            throw new MalformedDataException('Animation action is unknown.');
        }
        $runtime = $action->reader->readUnsignedVarLong();
        $data = $runtime->reader->readFloatLE();
        CodecSupport::validateFiniteFloat($data->value, 'Animation data', true);
        [$present, $reader] = CodecSupport::readBoolean($data->reader);
        $source = null;
        if ($present) {
            $read = $reader->readString(16); $source = $read->value; $reader = $read->reader;
            if (!in_array($source, ['none', 'build', 'mine', 'interact', 'attack', 'useitem', 'throwitem', 'dropitem', 'event'], true)) {
                throw new MalformedDataException('Animation swing source is unknown.');
            }
        }
        CodecSupport::requireEnd($reader);
        return new self($action->value, $runtime->value, $data->value, $source);
    }
}
