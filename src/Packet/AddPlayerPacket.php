<?php

declare(strict_types=1);

namespace Bedriox\Protocol\Packet;

use Bedriox\Protocol\Exception\InvalidValueException;
use Bedriox\Protocol\Exception\MalformedDataException;
use Bedriox\Protocol\Value\UnsignedLong;
use Bedriox\Protocol\Value\BuildPlatform;

/** Bounded AddPlayer variant with an empty hand, complete actor metadata, and no properties or links. */
final readonly class AddPlayerPacket implements Packet
{
    public function __construct(
        public string $uuid, public string $username, public UnsignedLong $runtimeEntityId, public string $platformChatId,
        public float $x, public float $y, public float $z, public float $motionX, public float $motionY, public float $motionZ,
        public float $pitch, public float $yaw, public float $headYaw, public int $gameType,
        public PlayerAbilities $abilities, public string $deviceId = '', public BuildPlatform $buildPlatform = BuildPlatform::Unknown,
        /** @var list<ActorMetadata> */ public array $metadata = [],
    ) {
        CodecSupport::uuidToWire($uuid);
        CodecSupport::validateString($username, CodecSupport::MAX_PLAYER_NAME_BYTES, 'Username');
        CodecSupport::validateString($platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Platform chat ID');
        CodecSupport::validateString($deviceId, CodecSupport::MAX_SHORT_STRING_BYTES, 'Device ID');
        foreach ([$x, $y, $z, $motionX, $motionY, $motionZ, $pitch, $yaw, $headYaw] as $value) {
            CodecSupport::validateFiniteFloat($value, 'Add-player vector');
        }
        if ($gameType < 0 || $gameType > 6) {
            throw new InvalidValueException('Add-player game type is invalid.');
        }
        ActorMetadataCollection::validate($metadata);
    }

    public function packetId(): int { return PacketIds::ADD_PLAYER; }

    public function encode(): string
    {
        $writer = CodecSupport::writer()->writeBytes(CodecSupport::uuidToWire($this->uuid))
            ->writeString($this->username, CodecSupport::MAX_PLAYER_NAME_BYTES)->writeUnsignedVarLong($this->runtimeEntityId)
            ->writeString($this->platformChatId, CodecSupport::MAX_SHORT_STRING_BYTES);
        foreach ([$this->x, $this->y, $this->z, $this->motionX, $this->motionY, $this->motionZ, $this->pitch, $this->yaw, $this->headYaw] as $value) {
            $writer = $writer->writeFloatLE($value);
        }
        $writer = $writer->writeSignedShortLE(0)->writeUnsignedShortLE(0)->writeUnsignedVarInt(0);
        $writer = CodecSupport::writeBoolean($writer, false)->writeUnsignedVarInt(0)->writeString('', 0)
            ->writeSignedVarInt($this->gameType);
        $writer = ActorMetadataCollection::write($writer, $this->metadata)
            ->writeUnsignedVarInt(0)->writeUnsignedVarInt(0);
        return $this->abilities->write($writer)->writeUnsignedVarInt(0)
            ->writeString($this->deviceId, CodecSupport::MAX_SHORT_STRING_BYTES)->writeSignedIntLE($this->buildPlatform->value)->toString();
    }

    public static function decode(string $bytes): self
    {
        $uuid = CodecSupport::reader($bytes)->readBytes(16); $username = $uuid->reader->readString(CodecSupport::MAX_PLAYER_NAME_BYTES);
        $runtime = $username->reader->readUnsignedVarLong(); $chat = $runtime->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES);
        $vectors = []; $reader = $chat->reader;
        for ($i = 0; $i < 9; ++$i) {
            $read = $reader->readFloatLE(); CodecSupport::validateFiniteFloat($read->value, 'Add-player vector', true);
            $vectors[] = $read->value; $reader = $read->reader;
        }
        $itemId = $reader->readSignedShortLE();
        $itemCount = $itemId->reader->readUnsignedShortLE();
        $itemAux = $itemCount->reader->readUnsignedVarInt();
        [$hasItemNetId, $reader] = CodecSupport::readBoolean($itemAux->reader);
        $blockRuntimeId = $reader->readUnsignedVarInt();
        $userData = $blockRuntimeId->reader->readString(CodecSupport::MAX_PACKET_BYTES);
        if ($itemId->value !== 0 || $itemCount->value !== 0 || $itemAux->value !== 0 || $hasItemNetId
            || $blockRuntimeId->value !== 0 || $userData->value !== '') {
            throw new MalformedDataException('Only empty-hand AddPlayer is supported by the MVP slice.');
        }
        $gameType = $userData->reader->readSignedVarInt();
        if ($gameType->value < 0 || $gameType->value > 6) { throw new MalformedDataException('Add-player game type is invalid.'); }
        [$metadata, $reader] = ActorMetadataCollection::read($gameType->reader);
        $ints = $reader->readUnsignedVarInt(); $floats = $ints->reader->readUnsignedVarInt();
        if ($ints->value !== 0 || $floats->value !== 0) {
            throw new MalformedDataException('AddPlayer actor properties are unsupported.');
        }
        [$abilities, $reader] = PlayerAbilities::read($floats->reader); $links = $reader->readUnsignedVarInt();
        if ($links->value !== 0) { throw new MalformedDataException('Only empty AddPlayer entity links are supported.'); }
        $device = $links->reader->readString(CodecSupport::MAX_SHORT_STRING_BYTES); $platform = $device->reader->readSignedIntLE();
        $buildPlatform = BuildPlatform::tryFrom($platform->value);
        if ($buildPlatform === null) { throw new MalformedDataException('Add-player build platform is invalid.'); }
        CodecSupport::requireEnd($platform->reader);
        return new self(
            CodecSupport::uuidFromWire($uuid->value), $username->value, $runtime->value, $chat->value,
            $vectors[0], $vectors[1], $vectors[2], $vectors[3], $vectors[4], $vectors[5], $vectors[6], $vectors[7], $vectors[8],
            $gameType->value, $abilities, $device->value, $buildPlatform, $metadata,
        );
    }
}
